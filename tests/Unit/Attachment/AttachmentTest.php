<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Attachment;

use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\MIME\Headers;
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

    #[Test]
    public function it_can_carry_a_new_representation_of_the_same_file(): void
    {
        $attachment = Attachment::cid(
            'some@uri.com',
            'name',
            'invoice.pdf',
            MemoryStream::create(),
        );

        $sealed = $attachment->withContent($stream = MemoryStream::create(), 'application/octet-stream');

        static::assertNotSame($attachment, $sealed);
        static::assertSame('<some@uri.com>', $sealed->id);
        static::assertSame('name', $sealed->name);
        static::assertSame('invoice.pdf', $sealed->filename);
        static::assertSame('application/octet-stream', $sealed->mimeType);
        static::assertSame($stream, $sealed->content);
    }

    #[Test]
    public function it_describes_itself_in_the_headers_it_travels_with(): void
    {
        $attachment = new Attachment(
            '<invoice@example.com>',
            'invoice',
            'invoice.pdf',
            'application/pdf',
            MemoryStream::create()
        );

        static::assertSame(
            [
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'application/pdf'],
                ['Content-Disposition', 'attachment; name="invoice"; filename="invoice.pdf"'],
            ],
            $attachment->headers()->pairs()
        );
    }

    #[Test]
    public function it_lets_an_extra_header_stand_in_for_the_one_it_would_describe(): void
    {
        $attachment = new Attachment(
            '<invoice@example.com>',
            'invoice',
            'invoice.xml',
            'application/xml',
            MemoryStream::create(),
            Headers::fromPairs([['Content-Type', 'application/xml; charset=UTF-8']])
        );

        // Substituted in place rather than appended, so the header this part describes appears once.
        static::assertSame(
            [
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'application/xml; charset=UTF-8'],
                ['Content-Disposition', 'attachment; name="invoice"; filename="invoice.xml"'],
            ],
            $attachment->headers()->pairs()
        );
    }

    #[Test]
    public function it_carries_an_extra_header_it_says_nothing_about_itself(): void
    {
        $attachment = new Attachment(
            '<invoice@example.com>',
            'invoice',
            'invoice.pdf',
            'application/pdf',
            MemoryStream::create(),
            Headers::fromPairs([['Content-Location', 'http://example.com/invoice.pdf']])
        );

        static::assertSame(
            'http://example.com/invoice.pdf',
            $attachment->headers()->get('Content-Location')
        );
        static::assertCount(4, $attachment->headers());
    }

    #[Test]
    public function it_keeps_every_extra_a_new_representation_does_not_contradict(): void
    {
        $attachment = new Attachment(
            '<invoice@example.com>',
            'invoice',
            'invoice.xml',
            'application/xml',
            MemoryStream::create(),
            Headers::fromPairs([
                ['Content-Type', 'application/xml; charset=UTF-8'],
                ['Content-Location', 'http://example.com/invoice.xml'],
            ])
        );

        $sealed = $attachment->withContent(MemoryStream::create(), 'application/octet-stream');

        // These are different bytes, so the media type the old ones were described by is dropped rather
        // than left to outrank the new one. It is the only fact this call changes, so it is the only
        // extra it drops.
        static::assertSame('application/octet-stream', $sealed->headers()->get('Content-Type'));
        static::assertSame('http://example.com/invoice.xml', $sealed->headers()->get('Content-Location'));
    }

    #[Test]
    public function it_can_be_built_from_the_headers_it_travelled_with(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'application/xml; charset=UTF-8'],
                ['Content-Disposition', 'attachment; name="invoice"; filename="invoice.xml"'],
                ['Content-Location', 'http://example.com/invoice.xml'],
            ]),
            $stream = MemoryStream::create()
        );

        static::assertSame('<invoice@example.com>', $attachment->id);
        static::assertSame('invoice', $attachment->name);
        static::assertSame('invoice.xml', $attachment->filename);
        static::assertSame('application/xml', $attachment->mimeType);
        static::assertSame($stream, $attachment->content);

        // The set that arrived is what travels on, so a parameter the scalars cannot hold survives.
        static::assertSame('application/xml; charset=UTF-8', $attachment->headers()->get('Content-Type'));
        static::assertSame('http://example.com/invoice.xml', $attachment->headers()->get('Content-Location'));
    }

    #[Test]
    public function it_falls_back_for_every_scalar_the_headers_do_not_carry(): void
    {
        $attachment = Attachment::fromHeaders(Headers::default(), MemoryStream::create());

        static::assertNotEmpty($attachment->id);
        static::assertSame('unknown', $attachment->name);
        static::assertSame('unknown', $attachment->filename);
        static::assertSame('application/octet-stream', $attachment->mimeType);
    }

    #[Test]
    public function it_falls_back_on_a_header_it_cannot_read(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([
                ['Content-Type', 'not a media type at all'],
                ['Content-Disposition', ';;; not a valid disposition'],
            ]),
            MemoryStream::create()
        );

        static::assertSame('application/octet-stream', $attachment->mimeType);
        static::assertSame('unknown', $attachment->name);
        static::assertSame('unknown', $attachment->filename);

        // Unreadable to us is not unreadable to the peer that wrote it, so it travels on as it arrived.
        static::assertSame('not a media type at all', $attachment->headers()->get('Content-Type'));
    }

    #[Test]
    public function it_generates_an_id_for_an_empty_content_id(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([['Content-ID', '']]),
            MemoryStream::create()
        );

        static::assertNotSame('', $attachment->id);
    }

    #[Test]
    public function it_can_carry_a_new_wire_envelope_for_the_same_file(): void
    {
        $attachment = Attachment::cid('some@uri.com', 'name', 'invoice.pdf', $stream = MemoryStream::create());

        $restored = $attachment->withHeaders(Headers::fromPairs([
            ['Content-ID', '<other@uri.com>'],
            ['Content-Type', 'text/plain; charset=us-ascii'],
            ['Content-Disposition', 'attachment; name="note"; filename="note.txt"'],
        ]));

        // Its identity and its bytes are the file; everything else is the envelope it travels in. An
        // envelope naming another part cannot re-address this one, in the scalar or on the wire.
        static::assertSame('<some@uri.com>', $restored->id);
        static::assertSame('<some@uri.com>', $restored->headers()->get('Content-ID'));
        static::assertSame($stream, $restored->content);
        static::assertSame('note', $restored->name);
        static::assertSame('note.txt', $restored->filename);
        static::assertSame('text/plain', $restored->mimeType);
        static::assertSame('text/plain; charset=us-ascii', $restored->headers()->get('Content-Type'));
    }
}
