<?php

namespace Soap\Psr18AttachmentMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Phpro\ResourceStream\Factory\TmpStream;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Riverline\MultiPartParser\Converters\PSR7;
use Riverline\MultiPartParser\StreamedPart;
use Soap\Psr18AttachmentMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentMiddleware\Exception\SoapMessageNotFoundException;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorageInterface;

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
        $start = StreamedPart::getHeaderOption($contentType, 'start');
        $soapType = StreamedPart::getHeaderOption($contentType, 'type', 'text/xml');
        if ($soapType === 'application/xop+xml') {
            $soapType = StreamedPart::getHeaderOption($contentType, 'start-info', 'application/soap+xml');
        }


        $mainPart = null;
        $attachments = $attachmentStorage->responseAttachments();
        foreach ($document->getParts() as $part) {
            $mimeType = $part->getMimeType();
            $id = $part->getHeader('Content-ID');

            if (($start && $id === $start) || $mimeType === $soapType) {
                $mainPart = $part;
                break;
            }

            $attachments->add(new Attachment(
                $id,
                $part->getFileName(),
                $mimeType,
                TmpStream::create()->write($part->getBody())->rewind(),
            ));
        }

        if (!$mainPart) {
            throw SoapMessageNotFoundException::insideMultipart($start, $soapType);
        }

        return $this->responseFactory->createResponse(
            $response->getStatusCode(),
            $this->streamFactory->createStream($mainPart->getBody())
        );
    }
}
