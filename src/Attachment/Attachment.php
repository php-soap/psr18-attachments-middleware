<?php

namespace Soap\Psr18AttachmentMiddleware\Attachment;

use Http\Message\MultipartStream\ApacheMimetypeHelper;
use Phpro\ResourceStream\ResourceStream;
use function Psl\SecureRandom\string;

final class Attachment
{
    public function __construct(
        public string $id,
        public string $filename,
        public string $mimeType,
        public ResourceStream $content,
    ) {
    }

    public static function create(
        string $filename,
        ResourceStream $content,
        ?string $mimeType = null,
    ): self {
        $mimeType ??= (new ApacheMimetypeHelper)->getMimetypeFromFilename($filename) ?? 'application/octet-stream';

        return new self(string(16), $filename, $mimeType, $content);
    }
}
