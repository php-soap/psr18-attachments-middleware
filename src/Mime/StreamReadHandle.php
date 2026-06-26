<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Mime;

use Psl\Async\CancellationTokenInterface;
use Psl\Async\NullCancellationToken;
use Psl\IO\ReadHandleConvenienceMethodsTrait;
use Psl\IO\ReadHandleInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Adapts a PSR-7 stream to a Psl\IO read handle so it can be fed to Psl\MIME's multipart parser.
 */
final readonly class StreamReadHandle implements ReadHandleInterface
{
    use ReadHandleConvenienceMethodsTrait;

    private const int DEFAULT_CHUNK_SIZE = 8192;

    public function __construct(
        private StreamInterface $stream,
    ) {
    }

    public function read(
        null|int $maxBytes = null,
        CancellationTokenInterface $cancellation = new NullCancellationToken(),
    ): string {
        return $this->stream->read($maxBytes ?? self::DEFAULT_CHUNK_SIZE);
    }

    public function tryRead(null|int $maxBytes = null): string
    {
        return $this->stream->read($maxBytes ?? self::DEFAULT_CHUNK_SIZE);
    }

    public function reachedEndOfDataSource(): bool
    {
        return $this->stream->eof();
    }
}
