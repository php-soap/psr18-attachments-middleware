<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Attachment;

use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\MIME\Headers;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Exception\InvalidAttachmentHeadersException;

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
    public function it_derives_the_wire_headers_from_the_scalars(): void
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
            $attachment->headers->pairs()
        );
    }

    #[Test]
    public function it_derives_the_wire_headers_of_a_created_attachment(): void
    {
        $attachment = Attachment::create('invoice', 'invoice.pdf', MemoryStream::create());

        static::assertSame($attachment->id, $attachment->headers->get('Content-ID'));
        static::assertSame('application/pdf', $attachment->headers->get('Content-Type'));
    }

    #[Test]
    public function it_derives_the_wire_headers_of_a_cid_attachment(): void
    {
        $attachment = Attachment::cid('some@uri.com', 'name', 'invoice.pdf', MemoryStream::create());

        static::assertSame('<some@uri.com>', $attachment->headers->get('Content-ID'));
        static::assertSame('application/pdf', $attachment->headers->get('Content-Type'));
    }

    #[Test]
    public function it_re_derives_the_content_type_header_of_a_new_representation(): void
    {
        $attachment = Attachment::cid('some@uri.com', 'name', 'invoice.pdf', MemoryStream::create());

        $sealed = $attachment->withContent(MemoryStream::create(), 'application/octet-stream');

        static::assertSame('application/octet-stream', $sealed->headers->get('Content-Type'));
        static::assertSame('<some@uri.com>', $sealed->headers->get('Content-ID'));
    }

    #[Test]
    public function it_can_build_an_attachment_from_the_headers_it_travelled_with(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'application/pdf'],
                ['Content-Disposition', 'attachment; name="invoice"; filename="invoice.pdf"'],
            ]),
            $stream = MemoryStream::create()
        );

        static::assertSame('<invoice@example.com>', $attachment->id);
        static::assertSame('invoice', $attachment->name);
        static::assertSame('invoice.pdf', $attachment->filename);
        static::assertSame('application/pdf', $attachment->mimeType);
        static::assertSame($stream, $attachment->content);
    }

    #[Test]
    public function it_keeps_content_type_parameters_in_the_header_set(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([
                ['Content-ID', '<report@example.com>'],
                ['Content-Type', 'application/xml; charset=UTF-8'],
            ]),
            MemoryStream::create()
        );

        static::assertSame('application/xml', $attachment->mimeType);
        static::assertSame('application/xml; charset=UTF-8', $attachment->headers->get('Content-Type'));
    }

    #[Test]
    public function it_generates_an_id_without_writing_it_into_the_header_set(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([['Content-Type', 'application/pdf']]),
            MemoryStream::create()
        );

        static::assertNotEmpty($attachment->id);
        static::assertFalse($attachment->headers->has('Content-ID'));
    }

    #[Test]
    public function it_generates_an_id_for_an_empty_content_id(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([['Content-ID', '']]),
            MemoryStream::create()
        );

        static::assertNotSame('', $attachment->id);
        static::assertSame('', $attachment->headers->get('Content-ID'));
    }

    #[Test]
    public function it_falls_back_to_octet_stream_without_writing_it_into_the_header_set(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([['Content-ID', '<invoice@example.com>']]),
            MemoryStream::create()
        );

        static::assertSame('application/octet-stream', $attachment->mimeType);
        static::assertFalse($attachment->headers->has('Content-Type'));
    }

    #[Test]
    public function it_falls_back_to_octet_stream_on_a_malformed_content_type(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'not a media type at all'],
            ]),
            MemoryStream::create()
        );

        static::assertSame('application/octet-stream', $attachment->mimeType);
        static::assertSame('not a media type at all', $attachment->headers->get('Content-Type'));
    }

    #[Test]
    public function it_falls_back_to_unknown_on_a_malformed_content_disposition(): void
    {
        $attachment = Attachment::fromHeaders(
            Headers::fromPairs([
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Disposition', ';;; not a valid disposition'],
            ]),
            MemoryStream::create()
        );

        static::assertSame('unknown', $attachment->name);
        static::assertSame('unknown', $attachment->filename);
    }

    #[Test]
    public function it_refuses_a_header_set_carrying_a_duplicate_profile_header(): void
    {
        $this->expectException(InvalidAttachmentHeadersException::class);
        $this->expectExceptionMessage('carries 2 "Content-Type" headers');

        Attachment::fromHeaders(
            Headers::fromPairs([
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'application/pdf'],
                ['content-type', 'text/plain'],
            ]),
            MemoryStream::create()
        );
    }

    #[Test]
    public function it_can_carry_a_new_wire_envelope_for_the_same_file(): void
    {
        $attachment = Attachment::cid('some@uri.com', 'name', 'invoice.pdf', MemoryStream::create());

        $restored = $attachment->withHeaders(Headers::fromPairs([
            ['Content-ID', '<some@uri.com>'],
            ['Content-Type', 'text/plain; charset=us-ascii'],
            ['Content-Disposition', 'attachment; name="note"; filename="note.txt"'],
        ]));

        static::assertSame('<some@uri.com>', $restored->id);
        static::assertSame('note', $restored->name);
        static::assertSame('note.txt', $restored->filename);
        static::assertSame('text/plain', $restored->mimeType);
        static::assertSame('text/plain; charset=us-ascii', $restored->headers->get('Content-Type'));
        static::assertSame($attachment->content, $restored->content);
    }

    #[Test]
    public function it_keeps_its_own_id_when_the_new_header_set_names_none(): void
    {
        $attachment = Attachment::cid('some@uri.com', 'name', 'invoice.pdf', MemoryStream::create());

        $restored = $attachment->withHeaders(Headers::fromPairs([['Content-Type', 'text/plain']]));

        static::assertSame('<some@uri.com>', $restored->id);
        static::assertFalse($restored->headers->has('Content-ID'));
    }

    #[Test]
    public function it_refuses_a_header_set_addressing_another_attachment(): void
    {
        $attachment = Attachment::cid('some@uri.com', 'name', 'invoice.pdf', MemoryStream::create());

        $this->expectException(InvalidAttachmentHeadersException::class);
        $this->expectExceptionMessage('address attachment "<other@uri.com>" instead of "<some@uri.com>"');

        $attachment->withHeaders(Headers::fromPairs([['Content-ID', '<other@uri.com>']]));
    }
}
