<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Storage;

use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Attachment\AttachmentsCollection;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;

final class AttachmentStorageTest extends TestCase
{
    #[Test]
    public function it_contains_request_attachments(): void
    {
        $attachments = $this->createStorage()->requestAttachments();

        static::assertEquals(new AttachmentsCollection(), $attachments);
    }

    #[Test]
    public function it_contains_response_attachments(): void
    {
        $attachments = $this->createStorage()->responseAttachments();

        static::assertEquals(new AttachmentsCollection(), $attachments);
    }

    #[Test]
    public function it_can_reset_request_attachments(): void
    {
        $storage = $this->createStorage();
        $attachments = $storage->requestAttachments()->add(
            Attachment::create('file', 'foo.png', MemoryStream::create())
        );
        $storage->resetRequestAttachments();
        $newAttachments = $storage->requestAttachments();

        static::assertNotSame($attachments, $newAttachments);
        static::assertEquals(new AttachmentsCollection(), $storage->requestAttachments());
    }

    #[Test]
    public function it_can_reset_response_attachments(): void
    {
        $storage = $this->createStorage();
        $attachments = $storage->responseAttachments()->add(
            Attachment::create('file', 'foo.png', MemoryStream::create())
        );
        $storage->resetResponseAttachments();
        $newAttachments = $storage->responseAttachments();

        static::assertNotSame($attachments, $newAttachments);
        static::assertEquals(new AttachmentsCollection(), $storage->responseAttachments());
    }

    private function createStorage(): AttachmentStorage
    {
        return new AttachmentStorage();
    }
}
