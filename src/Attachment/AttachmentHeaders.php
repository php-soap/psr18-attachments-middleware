<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Attachment;

use Psl\MIME\ContentDisposition;
use Psl\MIME\Exception\ExceptionInterface as MimeException;
use Psl\MIME\Headers;
use Psl\MIME\MediaType;
use Soap\Psr18AttachmentsMiddleware\Exception\InvalidHeaderValueException;

/**
 * Translates between an attachment's four facts and the MIME headers that carry them, both directions.
 *
 * Outbound `compose()` writes them; inbound the readers take them back out. An attachment holds the facts
 * and nothing about how they are spelled, which is what keeps MIME knowledge in one place.
 *
 * The readers answer null rather than guessing. A header this cannot read is still a header the peer that
 * wrote it meant something by, so it travels on untouched while the scalar it would have filled falls back.
 */
final class AttachmentHeaders
{
    /**
     * The part's identity, which is the one thing an extra header cannot restate. A collection looks an
     * attachment up by it, so a set that re-addressed the part on the wire would send a file under a name
     * nothing here answers to.
     */
    private const IDENTITY = 'Content-ID';

    /**
     * The headers an attachment with these facts travels with.
     *
     * An extra header replaces the one describing the same fact, in place, so the set never says a thing
     * twice. One naming a fact an attachment holds no scalar for is appended.
     */
    public static function compose(
        string $id,
        string $name,
        string $filename,
        string $mimeType,
        ?Headers $extra,
    ): Headers {
        $described = Headers::fromPairs([
            ['Content-ID', $id],
            ['Content-Type', $mimeType],
            ['Content-Disposition', sprintf(
                'attachment; name="%s"; filename="%s"',
                self::quoted('name', $name),
                self::quoted('filename', $filename)
            )],
        ]);

        if ($extra === null) {
            return $described;
        }

        $composed = [];
        foreach ($described->pairs() as [$header, $value]) {
            $composed[] = [
                $header,
                $header === self::IDENTITY ? $value : $extra->get($header) ?? $value,
            ];
        }

        foreach ($extra->pairs() as [$header, $value]) {
            if (!$described->has($header)) {
                $composed[] = [$header, $value];
            }
        }

        return Headers::fromPairs($composed);
    }

    /**
     * A parameter value as a quoted string, always quoted even where a bare token would be legal. Both are
     * spec-compliant and plenty of parsers only ever handle this one, so the shorter form wins nothing.
     *
     * The library's own serializer is not used here for that reason: it drops the quoting whenever the value
     * happens to be a token, which is a wire change with no upside on a protocol whose whole job is talking
     * to somebody else's stack.
     *
     * @throws InvalidHeaderValueException
     */
    private static function quoted(string $parameter, string $value): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw InvalidHeaderValueException::controlCharacter($parameter);
        }

        return addcslashes($value, '"\\');
    }

    public static function id(Headers $headers): ?string
    {
        $id = $headers->get('Content-ID');

        return $id === '' ? null : $id;
    }

    /**
     * The media type's essence. Its parameters stay in the header set and nowhere else, since a scalar
     * cannot hold them.
     */
    public static function mediaType(Headers $headers): ?string
    {
        $contentType = $headers->get('Content-Type');
        if ($contentType === null) {
            return null;
        }

        try {
            return MediaType::parse($contentType)->essence();
        } catch (MimeException) {
            return null;
        }
    }

    public static function name(Headers $headers): ?string
    {
        return self::disposition($headers)?->parameters->get('name');
    }

    /**
     * Psl reduces this to a basename, so a peer cannot name a path here.
     */
    public static function filename(Headers $headers): ?string
    {
        return self::disposition($headers)?->filename();
    }

    private static function disposition(Headers $headers): ?ContentDisposition
    {
        $contentDisposition = $headers->get('Content-Disposition');
        if ($contentDisposition === null) {
            return null;
        }

        try {
            return ContentDisposition::parse($contentDisposition);
        } catch (MimeException) {
            return null;
        }
    }
}
