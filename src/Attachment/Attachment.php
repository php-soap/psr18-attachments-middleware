<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Attachment;

use Phpro\ResourceStream\ResourceStream;
use Psl\MIME\Headers;
use Psl\MIME\MediaType;

final readonly class Attachment
{
    /**
     * @param string $filename - The name of the file inside the Content-Disposition header.
     * @param string $name - The name of the attachment inside the Content-Disposition header.
     * @param ResourceStream<resource> $content
     * @param ?Headers $extraHeaders - Headers to travel with beyond the three the four facts above describe.
     *                                 One naming the same fact stands in for the described header.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $filename,
        public string $mimeType,
        public ResourceStream $content,
        private ?Headers $extraHeaders = null,
    ) {
    }

    /**
     * @param ResourceStream<resource> $content
     */
    public static function create(
        string $name,
        string $filename,
        ResourceStream $content,
        ?string $mimeType = null,
        ?Headers $extraHeaders = null,
    ): self {
        $mimeType ??= MediaType::fromExtension(pathinfo($filename, PATHINFO_EXTENSION))?->toString() ?? 'application/octet-stream';

        return new self(
            IdGenerator::generate(),
            $name,
            $filename,
            $mimeType,
            $content,
            $extraHeaders
        );
    }

    /**
     * A named constructor for creating attachments for XOP.
     * This makes the ID "cid"-spec compliant.
     *
     * @see https://www.ietf.org/rfc/rfc2392.txt
     *
     * @param ResourceStream<resource> $content
     */
    public static function cid(
        string $uri,
        string $name,
        string $filename,
        ResourceStream $content,
        ?string $mimeType = null,
        ?Headers $extraHeaders = null,
    ): self {
        $mimeType ??= MediaType::fromExtension(pathinfo($filename, PATHINFO_EXTENSION))?->toString() ?? 'application/octet-stream';

        return new self(
            '<'.$uri.'>',
            $name,
            $filename,
            $mimeType,
            $content,
            $extraHeaders
        );
    }

    /**
     * A part as it arrived: its facts are read out of the headers, which travel on as they came.
     *
     * @param ResourceStream<resource> $content
     */
    public static function fromHeaders(Headers $headers, ResourceStream $content): self
    {
        return new self(
            AttachmentHeaders::id($headers) ?? IdGenerator::generate(),
            AttachmentHeaders::name($headers) ?? 'unknown',
            AttachmentHeaders::filename($headers) ?? 'unknown',
            AttachmentHeaders::mediaType($headers) ?? 'application/octet-stream',
            $content,
            $headers,
        );
    }

    /**
     * The headers this part travels with: the three its facts describe, with any extra it was given
     * substituted in place.
     */
    public function headers(): Headers
    {
        return AttachmentHeaders::compose(
            $this->id,
            $this->name,
            $this->filename,
            $this->mimeType,
            $this->extraHeaders
        );
    }

    /**
     * The same file in another representation: only the bytes and the media type change, so the Content-ID
     * keeps addressing it.
     *
     * The media type is also the only extra header this drops. One describing the bytes being replaced
     * would outrank the type they are being replaced with; every other extra describes the file, which is
     * still the same file.
     *
     * @param ResourceStream<resource> $content
     */
    public function withContent(ResourceStream $content, string $mimeType): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->filename,
            $mimeType,
            $content,
            $this->extraHeaders?->without('Content-Type')
        );
    }

    /**
     * The same file in another wire envelope: its identity and its bytes are untouched, and every other
     * fact is read back out of the new headers.
     */
    public function withHeaders(Headers $headers): self
    {
        return new self(
            $this->id,
            AttachmentHeaders::name($headers) ?? 'unknown',
            AttachmentHeaders::filename($headers) ?? 'unknown',
            AttachmentHeaders::mediaType($headers) ?? 'application/octet-stream',
            $this->content,
            $headers,
        );
    }
}
