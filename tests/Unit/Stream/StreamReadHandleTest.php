<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Stream;

use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Stream\StreamReadHandle;

final class StreamReadHandleTest extends TestCase
{
    #[Test]
    public function it_reads_a_psr7_stream_until_end_of_data_source(): void
    {
        $handle = new StreamReadHandle(Stream::create('hello world'));

        static::assertFalse($handle->reachedEndOfDataSource());
        static::assertSame('hello world', $handle->readAll());
        static::assertTrue($handle->reachedEndOfDataSource());
    }

    #[Test]
    public function it_reads_in_bounded_chunks(): void
    {
        $handle = new StreamReadHandle(Stream::create('hello world'));

        static::assertSame('hello', $handle->read(5));
        static::assertSame(' worl', $handle->read(5));
        static::assertSame('d', $handle->read(5));
    }
}
