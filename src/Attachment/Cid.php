<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Attachment;

use function Psl\Regex\replace;

/**
 * Translates between an attachment's Content-ID and the URI that addresses it from inside the
 * SOAP part, both directions of the same RFC 2392 rule.
 *
 * @see https://www.ietf.org/rfc/rfc2392.txt
 */
final class Cid
{
    /**
     * Takes the bracketed Content-ID an attachment carries, `<foo>`, and gives back `cid:foo`.
     *
     * @return non-empty-string
     */
    public static function uriFor(string $id): string
    {
        /** @var non-empty-string */
        return 'cid:'.replace($id, '/^<(.*)>$/', '$1');
    }

    /**
     * Takes `cid:foo` and gives back the bracketed Content-ID, `<foo>`.
     *
     * @return non-empty-string
     */
    public static function idFor(string $uri): string
    {
        /** @var non-empty-string */
        return replace($uri, '/^cid:(.*)$/', '<$1>');
    }
}
