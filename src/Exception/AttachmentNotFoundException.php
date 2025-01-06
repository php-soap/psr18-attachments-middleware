<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Exception;

use Soap\Engine\Exception\RuntimeException;

final class AttachmentNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Attachment with id "%s" can not be found.', $id));
    }
}
