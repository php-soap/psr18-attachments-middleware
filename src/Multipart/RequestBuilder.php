<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Soap\Psr18AttachmentMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorageInterface;

final readonly class RequestBuilder implements RequestBuilderInterface
{
    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface  $streamFactory,
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
        AttachmentStorageInterface $attachmentStorage,
        AttachmentType $attachmentType,
    ): RequestInterface {
        $attachments = $attachmentStorage->requestAttachments();
        if (!count($attachments)) {
            return $request;
        }

        $builder = new MultipartStreamBuilder($this->streamFactory);

        $builder->addData($request->getBody(), [
            'Content-Type' => match ($transportType) {
                AttachmentType::Swa => 'text/xml; charset=UTF-8',
                AttachmentType::Mtom => 'application/xop+xml; charset=UTF-8; type=application/soap+xml',
            },
            'Content-ID' => 'soaprequest'
        ]);

        /** @var Attachment $attachment */
        foreach ($attachments as $attachment) {
            $builder->addResource(
                $attachment->filename,
                $attachment->content->unwrap(),
                [
                    'filename' => $attachment->filename,
                    'headers' => [
                        'Content-ID' => $attachment->id,
                        'Content-Type' => $attachment->mimeType,
                        'Content-Transfer-Encoding' => 'binary',
                    ]
                ]
            );
        }

        $boundary = $builder->getBoundary();
        $multipartRequest = $this->requestFactory
            ->createRequest(
                $request->getMethod(),
                $request->getUri(),
            )
            ->withAddedHeader('Content-Type', match($transportType) {
                AttachmentType::Swa => 'multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="soaprequest"',
                AttachmentType::Mtom => 'multipart/related; type="application/xop+xml"; boundary="' . $boundary . '"; start="soaprequest"; start-info="application/soap+xml"',
            })
            ->withBody(
                $builder->build()
            );

        if ($transportType === AttachmentType::Swa) {
            $multipartRequest = $multipartRequest->withAddedHeader('SoapAction', $request->getHeaderLine('SoapAction'));
        }

        return $multipartRequest;
    }
}
