<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Phpro\ResourceStream\Factory\TmpStream;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Riverline\MultiPartParser\Converters\PSR7;
use Riverline\MultiPartParser\StreamedPart;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Attachment\IdGenerator;
use Soap\Psr18AttachmentsMiddleware\Exception\SoapMessageNotFoundException;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;
use function Psl\Type\nullable;
use function Psl\Type\string;

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

    public function __invoke(
        ResponseInterface $response,
        AttachmentStorageInterface $attachmentStorage,
        AttachmentType $attachmentType
    ): ResponseInterface {
        $document = PSR7::convert($response);
        if (!$document->isMultiPart()) {
            return $response;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $start = nullable(string())->coerce(StreamedPart::getHeaderOption($contentType, 'start'));
        $soapType = string()->coerce(StreamedPart::getHeaderOption($contentType, 'type', 'text/xml'));
        if ($soapType === 'application/xop+xml') {
            $soapType = string()->coerce(StreamedPart::getHeaderOption($contentType, 'start-info', 'application/soap+xml'));
        }

        $mainPart = null;
        $attachments = $attachmentStorage->responseAttachments();
        foreach ($document->getParts() as $part) {
            if (null === $mainPart && null === $start) {
                $mainPart = $part;
                continue;
            }
            $mimeType = $part->getMimeType();
            $id = string()->coerce($part->getHeader('Content-ID'));

            if ((isset($start) && $start && $id === $start) || $mimeType === $soapType) {
                $mainPart = $part;
                continue;
            }

            $attachments->add(new Attachment(
                $id ?: IdGenerator::generate(),
                $part->getName() ?? 'unknown',
                $part->getFileName() ?? 'unknown',
                $mimeType,
                TmpStream::create()->write($part->getBody())->rewind(),
            ));
        }

        if (!$mainPart) {
            throw SoapMessageNotFoundException::insideMultipart($start ?? '', $soapType);
        }

        return $this->responseFactory
            ->createResponse(
                $response->getStatusCode()
            )->withBody(
                $this->streamFactory->createStream($mainPart->getBody())
            );
    }
}
