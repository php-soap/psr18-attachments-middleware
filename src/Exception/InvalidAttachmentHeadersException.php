<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Exception;

use Soap\Engine\Exception\RuntimeException;

final class InvalidAttachmentHeadersException extends RuntimeException
{
    /**
     * A duplicate has no single canonical form, so a receiver that keeps one of the two and a sender
     * that kept the other agree on nothing further down.
     */
    public static function duplicateHeader(string $name, int $count): self
    {
        return new self(sprintf('The header set carries %d "%s" headers, while at most one is allowed.', $count, $name));
    }

    public static function addressAnotherAttachment(string $found, string $expected): self
    {
        return new self(sprintf(
            'The header set would address attachment "%s" instead of "%s".',
            $found,
            $expected
        ));
    }
}
