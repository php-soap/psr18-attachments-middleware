<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Exception;

use Soap\Engine\Exception\RuntimeException;

final class SoapMessageNotFoundException extends RuntimeException
{
    public static function insideMultipart(string $start, string $type): self
    {
        return new self(
            sprintf(
                'Soap message with id "%s" and type "%s" can not be found inside multipart response.',
                $start,
                $type
            )
        );
    }
}
