<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Stream;

use Phpro\ResourceStream\Factory\TmpStream;
use Phpro\ResourceStream\ResourceStream;
use Psl\IO\ReadHandleInterface;

/**
 * Drains a Psl\IO read handle into a temporary, seekable resource stream in fixed-size chunks.
 *
 * This keeps multipart bodies and parsed attachments memory-bounded: data is spilled to a
 * temporary file instead of being held in a single PHP string.
 */
final class HandleConverter
{
    private const int CHUNK_SIZE = 8192;

    /**
     * Drains the handle into a rewound temporary resource and transfers ownership to the caller.
     *
     * The backing {@see ResourceStream} is kept alive, so the returned resource is only closed by
     * whoever takes ownership of it (e.g. a PSR-7 stream created via createStreamFromResource()).
     *
     * @return resource
     */
    public static function intoResource(ReadHandleInterface $handle): mixed
    {
        return self::intoStream($handle)->keepAlive()->unwrap();
    }

    /**
     * @return ResourceStream<resource> A rewound temporary stream containing all data read from the handle.
     */
    public static function intoStream(ReadHandleInterface $handle): ResourceStream
    {
        $stream = TmpStream::create();
        while (true) {
            $chunk = $handle->read(self::CHUNK_SIZE);
            if ($chunk === '') {
                if ($handle->reachedEndOfDataSource()) {
                    break;
                }

                // An empty read that is not yet at EOF is a transient state (e.g. MultiPart\Parser's
                // read handle returns '' while advancing between parts); keep reading until EOF.
                continue;
            }

            $stream->write($chunk);
        }

        return $stream->rewind();
    }
}
