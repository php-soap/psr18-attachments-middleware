<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Attachment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Attachment\IdGenerator;

final class IdGeneratorTest extends TestCase
{
    #[Test]
    public function it_can_generate_a_random_id(): void
    {
        $id1 = IdGenerator::generate();
        $id2 = IdGenerator::generate();

        static::assertNotSame($id1, $id2);
        static::assertSame(16, mb_strlen($id1));
        static::assertSame(16, mb_strlen($id2));
    }
}
