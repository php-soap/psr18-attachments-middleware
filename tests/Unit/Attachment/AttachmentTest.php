<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Attachment;

use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;

final class AttachmentTest extends TestCase
{
    #[Test]
    public function it_can_load_attachment(): void
    {
        $attachment = new Attachment(
            'id',
            'name',
            'filename',
            'mimeType',
            $stream = MemoryStream::create()
        );

        static::assertSame('id', $attachment->id);
        static::assertSame('name', $attachment->name);
        static::assertSame('filename', $attachment->filename);
        static::assertSame('mimeType', $attachment->mimeType);
        static::assertSame($stream, $attachment->content);
    }

    #[Test]
    public function it_can_create_an_attachment(): void
    {
        $attachment = Attachment::create(
            'name',
            'filename.pdf',
            $stream = MemoryStream::create(),
        );

        static::assertNotEmpty($attachment->id);
        static::assertNotEmpty($attachment->name);
        static::assertSame('filename.pdf', $attachment->filename);
        static::assertSame('application/pdf', $attachment->mimeType);
        static::assertSame($stream, $attachment->content);
    }

    #[Test]
    public function it_can_create_a_cid_compliant_attachment(): void
    {
        $attachment = Attachment::cid(
            'some@uri.com',
            'name',
            'filename.pdf',
            $stream = MemoryStream::create(),
        );

        static::assertSame('<some@uri.com>', $attachment->id);
        static::assertSame('name', $attachment->name);
        static::assertSame('filename.pdf', $attachment->filename);
        static::assertSame('application/pdf', $attachment->mimeType);
        static::assertSame($stream, $attachment->content);
    }
}
