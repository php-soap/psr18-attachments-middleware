<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentMiddleware\Attachment;

use Http\Message\MultipartStream\ApacheMimetypeHelper;
use Phpro\ResourceStream\ResourceStream;

final class Attachment
{
    /**
     * @param ResourceStream<resource> $content
     */
    public function __construct(
        public string $id,
        public string $filename,
        public string $mimeType,
        public ResourceStream $content,
    ) {
    }

    /**
     * @param ResourceStream<resource> $content
     */
    public static function create(
        string $filename,
        ResourceStream $content,
        ?string $mimeType = null,
    ): self {
        $mimeType ??= (new ApacheMimetypeHelper)->getMimetypeFromFilename($filename) ?? 'application/octet-stream';

        return new self(
            IdGenerator::generate(),
            $filename,
            $mimeType,
            $content
        );
    }
}
