<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Attachment;

use Http\Message\MultipartStream\ApacheMimetypeHelper;
use Phpro\ResourceStream\ResourceStream;

final readonly class Attachment
{
    /**
     * @param string $filename - The name of the file inside the Content-Disposition header.
     * @param string $name - The name of the attachment inside the Content-Disposition header.
     * @param ResourceStream<resource> $content
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $filename,
        public string $mimeType,
        public ResourceStream $content,
    ) {
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
        $mimeType ??= (new ApacheMimetypeHelper)->getMimetypeFromFilename($filename) ?? 'application/octet-stream';

        return new self(
            IdGenerator::generate(),
            $name,
            $filename,
            $mimeType,
            $content
        );
    }
}
