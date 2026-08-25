<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Attachment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Attachment\Cid;

final class CidTest extends TestCase
{
    #[Test]
    public function it_can_build_a_uri_from_an_id(): void
    {
        static::assertSame('cid:some@uri.com', Cid::uriFor('<some@uri.com>'));
    }

    #[Test]
    public function it_can_build_an_id_from_a_uri(): void
    {
        static::assertSame('<some@uri.com>', Cid::idFor('cid:some@uri.com'));
    }

    #[Test]
    public function it_leaves_an_id_without_angle_brackets_alone(): void
    {
        static::assertSame('cid:generated-id', Cid::uriFor('generated-id'));
    }

    #[Test]
    public function it_carries_reserved_characters_across_verbatim(): void
    {
        // RFC 2392 reserves "/" within the mid scheme only, and its own worked example carries a
        // percent sequence across unchanged: Content-ID <foo4%25foo1@bar.net> is cid:foo4%25foo1@bar.net.
        // Encoding here would make a Content-ID that literally contains %2F indistinguishable from
        // one containing a slash, so neither direction touches them.
        static::assertSame('cid:a/b@ex.com', Cid::uriFor('<a/b@ex.com>'));
        static::assertSame('<a/b@ex.com>', Cid::idFor('cid:a/b@ex.com'));

        static::assertSame('cid:foo4%25foo1@bar.net', Cid::uriFor('<foo4%25foo1@bar.net>'));
        static::assertSame('<foo4%25foo1@bar.net>', Cid::idFor('cid:foo4%25foo1@bar.net'));
    }

    #[Test]
    public function it_leaves_the_at_sign_of_an_addr_spec_alone(): void
    {
        static::assertSame('cid:invoice@example.com', Cid::uriFor('<invoice@example.com>'));
        static::assertSame('<invoice@example.com>', Cid::idFor('cid:invoice@example.com'));
    }

    #[Test]
    public function it_leaves_a_uri_without_the_cid_scheme_alone(): void
    {
        static::assertSame('http://example.com/x', Cid::idFor('http://example.com/x'));
    }
}
