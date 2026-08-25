<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Attachment;

use Phpro\ResourceStream\ResourceStream;
use Psl\MIME\ContentDisposition;
use Psl\MIME\Exception\ExceptionInterface as MimeException;
use Psl\MIME\Headers;
use Psl\MIME\MediaType;
use Soap\Psr18AttachmentsMiddleware\Exception\InvalidAttachmentHeadersException;

final class Attachment
{
    /**
     * The headers this part travels with, and the only place a header parameter such as a charset lives.
     *
     * Either the scalars are the input and this set is derived from them, or this set is the input and the
     * scalars are read out of it. No construction path takes both, so the two cannot disagree.
     */
    public private(set) Headers $headers;

    /**
     * The headers a peer considers when it covers a part's metadata as well as its bytes.
     *
     * @see https://docs.oasis-open.org/wss/oasis-wss-SwAProfile-1.1-spec-os.pdf
     *
     * @var list<string>
     */
    private const array PROFILE_HEADERS = [
        'Content-Description',
        'Content-Disposition',
        'Content-ID',
        'Content-Location',
        'Content-Type',
    ];

    /**
     * @param string $filename - The name of the file inside the Content-Disposition header.
     * @param string $name - The name of the attachment inside the Content-Disposition header.
     * @param ResourceStream<resource> $content
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly ResourceStream $content,
    ) {
        $this->headers = Headers::fromPairs([
            ['Content-ID', $id],
            ['Content-Type', $mimeType],
            ['Content-Disposition', sprintf('attachment; name="%s"; filename="%s"', $name, $filename)],
        ]);
    }

    /**
     * @param ResourceStream<resource> $content
     */
    public static function create(
        string $name,
        string $filename,
        ResourceStream $content,
        ?string $mimeType = null,
    ): self {
        $mimeType ??= MediaType::fromExtension(pathinfo($filename, PATHINFO_EXTENSION))?->toString() ?? 'application/octet-stream';

        return new self(
            IdGenerator::generate(),
            $name,
            $filename,
            $mimeType,
            $content
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
    ): self {
        $mimeType ??= MediaType::fromExtension(pathinfo($filename, PATHINFO_EXTENSION))?->toString() ?? 'application/octet-stream';

        return new self(
            '<'.$uri.'>',
            $name,
            $filename,
            $mimeType,
            $content
        );
    }

    /**
     * A part as it arrived: the header set is the truth and the scalars are read out of it.
     *
     * A scalar with no header to read from falls back, and a fallback is never written into the header set.
     * A peer that sent no Content-Type covered the absence of one, so recording a substitute here would make
     * what it covered irreproducible.
     *
     * @param ResourceStream<resource> $content
     *
     * @throws InvalidAttachmentHeadersException
     */
    public static function fromHeaders(Headers $headers, ResourceStream $content): self
    {
        self::assertNoDuplicateProfileHeader($headers);

        $id = $headers->get('Content-ID') ?? '';

        return self::readScalarsFrom($headers, $id !== '' ? $id : IdGenerator::generate(), $content);
    }

    /**
     * The same file in another representation: only the bytes and the media type change,
     * so the Content-ID keeps addressing it.
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
            $content
        );
    }

    /**
     * The same file in another wire envelope: the bytes are untouched and the scalars are read out of the
     * new header set.
     *
     * @throws InvalidAttachmentHeadersException when the set names a Content-ID other than this one's
     */
    public function withHeaders(Headers $headers): self
    {
        self::assertNoDuplicateProfileHeader($headers);

        $id = $headers->get('Content-ID');
        if ($id !== null && $id !== $this->id) {
            throw InvalidAttachmentHeadersException::addressAnotherAttachment($id, $this->id);
        }

        return self::readScalarsFrom($headers, $this->id, $this->content);
    }

    /**
     * @param ResourceStream<resource> $content
     */
    private static function readScalarsFrom(Headers $headers, string $id, ResourceStream $content): self
    {
        $disposition = self::parseDisposition($headers->get('Content-Disposition'));

        $attachment = new self(
            $id,
            $disposition?->parameters->get('name') ?? 'unknown',
            $disposition?->filename() ?? 'unknown',
            self::parseEssence($headers->get('Content-Type')) ?? 'application/octet-stream',
            $content
        );
        $attachment->headers = $headers;

        return $attachment;
    }

    private static function parseEssence(?string $contentType): ?string
    {
        if ($contentType === null) {
            return null;
        }

        try {
            return MediaType::parse($contentType)->essence();
        } catch (MimeException) {
            // The header travels as it arrived either way, so a value we cannot read costs the scalar
            // rather than the message.
            return null;
        }
    }

    private static function parseDisposition(?string $contentDisposition): ?ContentDisposition
    {
        if ($contentDisposition === null) {
            return null;
        }

        try {
            return ContentDisposition::parse($contentDisposition);
        } catch (MimeException) {
            // A malformed Content-Disposition falls back to the 'unknown' name and filename, matching the
            // lenient behaviour the multipart parser has always had.
            return null;
        }
    }

    /**
     * @throws InvalidAttachmentHeadersException
     */
    private static function assertNoDuplicateProfileHeader(Headers $headers): void
    {
        foreach (self::PROFILE_HEADERS as $name) {
            $count = count($headers->all($name));
            if ($count > 1) {
                throw InvalidAttachmentHeadersException::duplicateHeader($name, $count);
            }
        }
    }
}
