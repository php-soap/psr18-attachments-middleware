<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Psl\IO\MemoryHandle;
use Psl\IO\ReadStreamHandle;
use Psl\MIME\Headers;
use Psl\MIME\MultiPart\Related;
use Psl\MIME\Part\Part;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Mime\StreamCopy;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;
use Soap\Psr18Transport\HttpBinding\SoapActionDetector;
use function Psl\Result\try_catch;

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

    /**
     * Note: each request attachment is streamed from its underlying resource into the multipart body,
     * leaving its cursor at EOF. The provided attachments are single-use and must not be reused after this call.
     */
    public function __invoke(
        RequestInterface $request,
        AttachmentStorageInterface $attachmentStorage,
        AttachmentType $attachmentType,
    ): RequestInterface {
        $attachments = $attachmentStorage->requestAttachments();
        if (!count($attachments)) {
            return $request;
        }

        $contentTypeAction = '';
        if ($attachmentType === AttachmentType::Mtom) {
            $contentTypeAction = try_catch(
                static fn () => SoapActionDetector::detectFromRequest($request),
                static fn () => '',
            );
            $contentTypeAction = $contentTypeAction ? '; action=\"'.$contentTypeAction.'\"' : '';
        }

        $related = new Related(
            new Part(
                Headers::fromPairs([
                    ['Content-Type', match ($attachmentType) {
                        AttachmentType::Swa => 'text/xml; charset=UTF-8',
                        AttachmentType::Mtom => 'application/xop+xml; charset=UTF-8; type="application/soap+xml'.$contentTypeAction.'"',
                    }],
                    ['Content-ID', '<soaprequest@main>'],
                ]),
                new MemoryHandle((string) $request->getBody()),
            )
        );

        /** @var Attachment $attachment */
        foreach ($attachments as $attachment) {
            $related->addPart(new Part(
                Headers::fromPairs([
                    ['Content-ID', $attachment->id],
                    ['Content-Type', $attachment->mimeType],
                    ['Content-Disposition', sprintf(
                        'attachment; name="%s"; filename="%s"',
                        $attachment->name,
                        $attachment->filename
                    )],
                    ['Content-Transfer-Encoding', 'binary'],
                ]),
                new ReadStreamHandle($attachment->content->rewind()->unwrap()),
            ));
        }

        $boundary = $related->boundary;
        $multipartRequest = $this->requestFactory
            ->createRequest(
                $request->getMethod(),
                $request->getUri(),
            )
            ->withAddedHeader('Content-Type', match($attachmentType) {
                AttachmentType::Swa => 'multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="<soaprequest@main>"',
                AttachmentType::Mtom => 'multipart/related; type="application/xop+xml"; boundary="' . $boundary . '"; start="<soaprequest@main>"; start-info="application/soap+xml'.$contentTypeAction.'"',
            })
            ->withBody(
                $this->streamFactory->createStreamFromResource(
                    StreamCopy::toResource($related->body())
                )
            );

        if ($attachmentType === AttachmentType::Swa) {
            $multipartRequest = $multipartRequest->withAddedHeader('SoapAction', $request->getHeaderLine('SoapAction'));
        }

        return $multipartRequest;
    }
}
