<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Mime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\IO\MemoryHandle;
use Soap\Psr18AttachmentsMiddleware\Mime\StreamCopy;

final class StreamCopyTest extends TestCase
{
    #[Test]
    public function it_copies_handle_contents_across_chunk_boundaries(): void
    {
        // Larger than the 8192-byte chunk size to exercise the multi-read copy loop.
        $payload = str_repeat('0123456789abcdef', 2000);

        $stream = StreamCopy::toResourceStream(new MemoryHandle($payload));

        static::assertSame($payload, $stream->getContents());
    }

    #[Test]
    public function it_exposes_a_rewound_readable_resource_via_to_resource(): void
    {
        $payload = str_repeat('payload-', 2048);

        $resource = StreamCopy::toResource(new MemoryHandle($payload));

        static::assertIsResource($resource);
        static::assertSame($payload, stream_get_contents($resource));
    }
}
