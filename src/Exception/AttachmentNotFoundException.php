<?php

namespace Soap\Psr18AttachmentMiddleware\Exception;

use Soap\Engine\Exception\RuntimeException;

final class AttachmentNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Attachment with id "%s" can not be found.', $id));
    }
}
