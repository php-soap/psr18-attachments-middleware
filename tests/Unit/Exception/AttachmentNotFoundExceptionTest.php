<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Engine\Exception\RuntimeException;
use Soap\Psr18AttachmentsMiddleware\Exception\AttachmentNotFoundException;

final class AttachmentNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_can_throw_by_id(): void
    {
        $exception = AttachmentNotFoundException::withId('foo');

        $this->expectException(AttachmentNotFoundException::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Attachment with id "foo" can not be found.');
        throw $exception;
    }
}
