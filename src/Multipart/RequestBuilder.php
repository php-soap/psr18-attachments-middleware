<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;

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

        $builder->addData(
            (string) $request->getBody(),
            [
                'Content-Type' => match ($attachmentType) {
                    AttachmentType::Swa => 'text/xml; charset=UTF-8',
                    AttachmentType::Mtom => 'application/xop+xml; charset=UTF-8; type=application/soap+xml',
                },
                'Content-ID' => 'soaprequest'
            ]
        );

        /** @var Attachment $attachment */
        foreach ($attachments as $attachment) {
            $builder->addData(
                $attachment->content->rewind()->unwrap(),
                [
                    'Content-ID' => $attachment->id,
                    'Content-Type' => $attachment->mimeType,
                    'Content-Disposition' => sprintf(
                        'attachment; name="%s"; filename="%s"',
                        $attachment->name,
                        $attachment->filename
                    ),
                    'Content-Transfer-Encoding' => 'binary',
                ]
            );
        }

        $boundary = $builder->getBoundary();
        $multipartRequest = $this->requestFactory
            ->createRequest(
                $request->getMethod(),
                $request->getUri(),
            )
            ->withAddedHeader('Content-Type', match($attachmentType) {
                AttachmentType::Swa => 'multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="soaprequest"',
                AttachmentType::Mtom => 'multipart/related; type="application/xop+xml"; boundary="' . $boundary . '"; start="soaprequest"; start-info="application/soap+xml"',
            })
            ->withBody(
                $builder->build()
            );

        if ($attachmentType === AttachmentType::Swa) {
            $multipartRequest = $multipartRequest->withAddedHeader('SoapAction', $request->getHeaderLine('SoapAction'));
        }

        return $multipartRequest;
    }
}
