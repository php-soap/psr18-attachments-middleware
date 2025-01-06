<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Phpro\ResourceStream\Factory\TmpStream;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Riverline\MultiPartParser\Converters\PSR7;
use Riverline\MultiPartParser\StreamedPart;
use Soap\Psr18AttachmentMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentMiddleware\Attachment\IdGenerator;
use Soap\Psr18AttachmentMiddleware\Exception\SoapMessageNotFoundException;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorageInterface;
use function Psl\Type\string;

final class ResponseBuilder
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public static function default(): self
    {
        return new self(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );
    }

    public function __invoke(ResponseInterface $response, AttachmentStorageInterface $attachmentStorage): ResponseInterface
    {
        $document = PSR7::convert($response);
        if (!$document->isMultiPart()) {
            return $response;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $start = string()->coerce(StreamedPart::getHeaderOption($contentType, 'start'));
        $soapType = string()->coerce(StreamedPart::getHeaderOption($contentType, 'type', 'text/xml'));
        if ($soapType === 'application/xop+xml') {
            $soapType = string()->coerce(StreamedPart::getHeaderOption($contentType, 'start-info', 'application/soap+xml'));
        }

        $mainPart = null;
        $attachments = $attachmentStorage->responseAttachments();
        foreach ($document->getParts() as $part) {
            $mimeType = $part->getMimeType();
            $id = string()->coerce($part->getHeader('Content-ID'));

            if (($start && $id === $start) || $mimeType === $soapType) {
                $mainPart = $part;
                break;
            }

            $attachments->add(new Attachment(
                $id ?: IdGenerator::generate(),
                $part->getFileName() ?? 'unknown',
                $mimeType,
                TmpStream::create()->write($part->getBody())->rewind(),
            ));
        }

        if (!$mainPart) {
            throw SoapMessageNotFoundException::insideMultipart($start, $soapType);
        }

        return $this->responseFactory
            ->createResponse(
                $response->getStatusCode()
            )->withBody(
                $this->streamFactory->createStream($mainPart->getBody())
            );
    }
}
