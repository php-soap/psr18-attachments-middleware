<?php

namespace Soap\Psr18AttachmentMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Soap\Psr18AttachmentMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorage;

final class RequestBuilder
{
    public function __construct(
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public static function default(): self
    {
        return new self(
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );
    }

    public function __invoke(
        RequestInterface $request,
        AttachmentStorage $attachmentStorage,
        TransportType $transportType
    ): RequestInterface {
        $attachments = $attachmentStorage->requestAttachments();
        if (!count($attachments)) {
            return $request;
        }

        $builder = new MultipartStreamBuilder($this->streamFactory);

        $builder->addData($request->getBody(), [
            'Content-Type' => match ($transportType) {
                TransportType::Swa => 'text/xml; charset=UTF-8',
                TransportType::Mtom => 'application/xop+xml; charset=UTF-8; type=application/soap+xml',
            },
            'Content-ID' => 'soaprequest'
        ]);

        /** @var Attachment $attachment */
        foreach ($attachments as $attachment) {
            $builder->addResource($attachment->content->unwrap(), $attachment->mimeType, [
                'filename' => $attachment->filename,
                'headers' => [
                    'Content-ID' => $attachment->id,
                    'Content-Type' => $attachment->mimeType,
                    'Content-Transfer-Encoding' => 'binary',
                ]
            ]);
        }

        $boundary = $builder->getBoundary();
        $multipartRequest = $this->requestFactory->createRequest(
            $request->getMethod(),
            $request->getUri(),
            $builder->build()
        )->withAddedHeader('Content-Type', match($transportType) {
            TransportType::Swa => 'multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="soaprequest"',
            TransportType::Mtom => 'multipart/related; type="application/xop+xml"; boundary="' . $boundary . '"; start="soaprequest"; start-info="application/soap+xml"',
        });

        if ($transportType === TransportType::Swa) {
            $multipartRequest = $multipartRequest->withAddedHeader('SoapAction', $request->getHeaderLine('SoapAction'));
        }

        return $multipartRequest;
    }
}
