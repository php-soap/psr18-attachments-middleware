<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Psl\MIME\MediaType;
use Psl\MIME\MultiPart\Parser;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Exception\SoapMessageNotFoundException;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;
use Soap\Psr18AttachmentsMiddleware\Stream\HandleConverter;
use Soap\Psr18AttachmentsMiddleware\Stream\StreamReadHandle;

final readonly class ResponseBuilder implements ResponseBuilderInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public static function default(): self
    {
        return new self(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );
    }

    /**
     * @psalm-suppress NoInterfaceProperties Psl\MIME\Part\PartInterface exposes $headers and $mediaType as
     *     PHP 8.4 virtual interface properties, which psalm 6.13 cannot model yet.
     */
    public function __invoke(
        ResponseInterface $response,
        AttachmentStorageInterface $attachmentStorage,
        AttachmentType $attachmentType
    ): ResponseInterface {
        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType === '') {
            return $response;
        }

        $mediaType = MediaType::parse($contentType);
        $boundary = $mediaType->parameters->get('boundary');
        if ($mediaType->type !== 'multipart' || $boundary === null || $boundary === '') {
            return $response;
        }

        $start = $mediaType->parameters->get('start');
        $soapType = $mediaType->parameters->get('type') ?? 'text/xml';
        if ($soapType === 'application/xop+xml') {
            $soapType = $mediaType->parameters->get('start-info') ?? 'application/soap+xml';
        }

        $mainPart = null;
        $attachments = $attachmentStorage->responseAttachments();
        $handle = new StreamReadHandle($response->getBody());
        foreach ((new Parser($boundary))->parse($handle) as $part) {
            $id = $part->headers->get('Content-ID') ?? '';

            // When no "start" is provided, the first part should be considered the main part.
            // @see https://datatracker.ietf.org/doc/html/rfc2387#section-3.2
            if (null === $mainPart && null === $start) {
                $mainPart = HandleConverter::intoStream($part->body());
                continue;
            }

            if ($start !== null && $id === $start) {
                $mainPart = HandleConverter::intoStream($part->body());
                continue;
            }

            $attachments->add(Attachment::fromHeaders(
                $part->headers,
                HandleConverter::intoStream($part->body()),
            ));
        }

        if (null === $mainPart) {
            throw SoapMessageNotFoundException::insideMultipart($start ?? '', $soapType);
        }

        return $this->responseFactory
            ->createResponse(
                $response->getStatusCode()
            )->withBody(
                $this->streamFactory->createStreamFromResource($mainPart->keepAlive()->rewind()->unwrap())
            );
    }
}
