<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Attachment;

use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Attachment\AttachmentsCollection;
use Soap\Psr18AttachmentsMiddleware\Exception\AttachmentNotFoundException;

final class AttachmentsCollectionTest extends TestCase
{
    #[Test]
    public function it_can_contain_attachments(): void
    {
        $collection = new AttachmentsCollection(
            $attachment1 = Attachment::create('file', 'filename.pdf', MemoryStream::create()),
            $attachment2 = Attachment::create('file', 'filename.jpg', MemoryStream::create()),
        );

        static::assertCount(2, $collection);
        static::assertSame([$attachment1, $attachment2], [...$collection]);
    }

    #[Test]
    public function it_can_add_an_item_mutably(): void
    {
        $collection = new AttachmentsCollection();
        $collection->add($attachment = Attachment::create('file', 'filename.pdf', MemoryStream::create()));

        static::assertCount(1, $collection);
        static::assertSame([$attachment], [...$collection]);
    }

    #[Test]
    public function it_can_find_an_attachment_by_id(): void
    {
        $collection = new AttachmentsCollection(
            $attachment1 = Attachment::create('file', 'filename.pdf', MemoryStream::create()),
            $attachment2 = Attachment::create('file', 'filename.jpg', MemoryStream::create()),
        );

        static::assertSame($attachment1, $collection->findById($attachment1->id));
    }

    #[Test]
    public function it_can_fail_finding_an_attachment_by_id(): void
    {
        $collection = new AttachmentsCollection(
            Attachment::create('file', 'filename.pdf', MemoryStream::create()),
        );

        $this->expectExceptionObject(AttachmentNotFoundException::withId('not-found'));
        $collection->findById('not-found');
    }
}
