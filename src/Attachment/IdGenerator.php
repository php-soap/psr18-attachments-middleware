<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentMiddleware\Attachment;

use function Psl\SecureRandom\string;

final class IdGenerator
{
    public static function generate(): string
    {
        return string(16);
    }
}