<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Attachment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\MIME\ContentDisposition;
use Psl\MIME\Headers;
use Soap\Psr18AttachmentsMiddleware\Attachment\AttachmentHeaders;
use Soap\Psr18AttachmentsMiddleware\Exception\InvalidHeaderValueException;

final class AttachmentHeadersTest extends TestCase
{
    #[Test]
    public function it_composes_the_three_headers_an_attachment_describes(): void
    {
        static::assertSame(
            [
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'application/pdf'],
                ['Content-Disposition', 'attachment; name="invoice"; filename="invoice.pdf"'],
            ],
            AttachmentHeaders::compose(
                '<invoice@example.com>',
                'invoice',
                'invoice.pdf',
                'application/pdf',
                null
            )->pairs()
        );
    }

    #[Test]
    public function it_substitutes_an_extra_in_place_and_appends_the_rest(): void
    {
        $composed = AttachmentHeaders::compose(
            '<invoice@example.com>',
            'invoice',
            'invoice.pdf',
            'application/pdf',
            Headers::fromPairs([
                ['content-type', 'application/pdf; version=1.7'],
                ['Content-Location', 'http://example.com/invoice.pdf'],
            ])
        );

        static::assertSame(
            [
                ['Content-ID', '<invoice@example.com>'],
                ['Content-Type', 'application/pdf; version=1.7'],
                ['Content-Disposition', 'attachment; name="invoice"; filename="invoice.pdf"'],
                ['Content-Location', 'http://example.com/invoice.pdf'],
            ],
            $composed->pairs()
        );
    }

    #[Test]
    public function it_escapes_a_name_or_filename_that_would_close_the_quoting(): void
    {
        $composed = AttachmentHeaders::compose(
            '<invoice@example.com>',
            'in"voice',
            're;port.pdf',
            'application/pdf',
            null
        );

        // The value has to survive the trip, and a header a peer cannot parse loses the whole part.
        $disposition = ContentDisposition::parse($composed->get('Content-Disposition'));
        static::assertSame('in"voice', $disposition->parameters->get('name'));
        static::assertSame('re;port.pdf', $disposition->parameters->get('filename'));
    }

    #[Test]
    public function it_refuses_a_filename_that_would_forge_a_header(): void
    {
        // A line break here does not produce a broken header, it produces extra ones: everything after it
        // reads as a header of its own, and a Content-Transfer-Encoding landing there is honoured ahead of
        // the real one.
        $this->expectException(InvalidHeaderValueException::class);
        $this->expectExceptionMessage('"filename" carries a control character');

        AttachmentHeaders::compose(
            '<invoice@example.com>',
            'invoice',
            "evil\r\nContent-Transfer-Encoding: base64",
            'application/pdf',
            null
        );
    }

    #[Test]
    public function it_refuses_a_name_that_would_forge_a_header(): void
    {
        $this->expectException(InvalidHeaderValueException::class);
        $this->expectExceptionMessage('"name" carries a control character');

        AttachmentHeaders::compose(
            '<invoice@example.com>',
            "evil\r\nX-Injected: yes",
            'invoice.pdf',
            'application/pdf',
            null
        );
    }

    #[Test]
    public function it_never_lets_an_extra_re_address_the_part(): void
    {
        $composed = AttachmentHeaders::compose(
            '<invoice@example.com>',
            'invoice',
            'invoice.pdf',
            'application/pdf',
            Headers::fromPairs([['Content-ID', '<other@example.com>']])
        );

        // The Content-ID is the part's identity rather than part of the envelope it travels in, and a
        // collection looks an attachment up by it. An extra that overrode it would address a different file.
        static::assertSame('<invoice@example.com>', $composed->get('Content-ID'));
        static::assertCount(3, $composed);
    }

    #[Test]
    public function it_reads_the_content_id(): void
    {
        static::assertSame(
            '<invoice@example.com>',
            AttachmentHeaders::id(Headers::fromPairs([['Content-ID', '<invoice@example.com>']]))
        );
        static::assertNull(AttachmentHeaders::id(Headers::default()));
        static::assertNull(AttachmentHeaders::id(Headers::fromPairs([['Content-ID', '']])));
    }

    #[Test]
    public function it_reads_a_media_type_down_to_its_essence(): void
    {
        static::assertSame(
            'application/xml',
            AttachmentHeaders::mediaType(Headers::fromPairs([['Content-Type', 'application/xml; charset=UTF-8']]))
        );
        static::assertNull(AttachmentHeaders::mediaType(Headers::default()));
        static::assertNull(
            AttachmentHeaders::mediaType(Headers::fromPairs([['Content-Type', 'not a media type at all']]))
        );
    }

    #[Test]
    public function it_reads_the_name_and_the_filename_out_of_a_disposition(): void
    {
        $headers = Headers::fromPairs([
            ['Content-Disposition', 'attachment; name="invoice"; filename="invoice.pdf"'],
        ]);

        static::assertSame('invoice', AttachmentHeaders::name($headers));
        static::assertSame('invoice.pdf', AttachmentHeaders::filename($headers));
    }

    #[Test]
    public function it_reads_nothing_out_of_a_disposition_it_cannot_parse(): void
    {
        $headers = Headers::fromPairs([['Content-Disposition', ';;; not a valid disposition']]);

        static::assertNull(AttachmentHeaders::name($headers));
        static::assertNull(AttachmentHeaders::filename($headers));
    }

    #[Test]
    public function it_strips_a_directory_out_of_a_filename(): void
    {
        // Psl sanitizes it; pinned here because this value reaches a caller that may write a file with it.
        $headers = Headers::fromPairs([
            ['Content-Disposition', 'attachment; filename="../../etc/passwd"'],
        ]);

        static::assertSame('passwd', AttachmentHeaders::filename($headers));
    }
}
