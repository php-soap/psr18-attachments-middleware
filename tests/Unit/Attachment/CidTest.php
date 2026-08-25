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
    public function it_leaves_a_uri_without_the_cid_scheme_alone(): void
    {
        static::assertSame('http://example.com/x', Cid::idFor('http://example.com/x'));
    }
}
