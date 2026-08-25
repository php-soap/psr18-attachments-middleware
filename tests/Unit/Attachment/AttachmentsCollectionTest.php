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

    #[Test]
    public function it_can_find_an_attachment_by_predicate(): void
    {
        $collection = new AttachmentsCollection(
            Attachment::create('file', 'filename.pdf', MemoryStream::create()),
            $attachment2 = Attachment::create('file', 'filename.jpg', MemoryStream::create()),
        );

        static::assertSame(
            $attachment2,
            $collection->find(static fn (Attachment $attachment): bool => $attachment->filename === 'filename.jpg'),
        );
    }

    #[Test]
    public function it_returns_null_when_no_attachment_matches_the_predicate(): void
    {
        $collection = new AttachmentsCollection(
            Attachment::create('file', 'filename.pdf', MemoryStream::create()),
        );

        static::assertNull($collection->find(static fn (Attachment $attachment): bool => false));
    }

    #[Test]
    public function it_can_replace_an_attachment_in_place(): void
    {
        $collection = new AttachmentsCollection(
            $attachment1 = Attachment::create('file', 'filename.pdf', MemoryStream::create()),
            $attachment2 = Attachment::create('file', 'filename.jpg', MemoryStream::create()),
        );

        $replaced = $attachment1->withContent(MemoryStream::create(), 'application/octet-stream');
        $collection->replace($replaced);

        static::assertSame([$replaced, $attachment2], [...$collection]);
    }

    #[Test]
    public function it_can_fail_replacing_an_attachment_that_is_not_there(): void
    {
        $collection = new AttachmentsCollection(
            Attachment::create('file', 'filename.pdf', MemoryStream::create()),
        );

        $stranger = Attachment::cid('not@there.com', 'file', 'filename.pdf', MemoryStream::create());

        $this->expectExceptionObject(AttachmentNotFoundException::withId('<not@there.com>'));
        $collection->replace($stranger);
    }
}
