<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Engine\Exception\RuntimeException;
use Soap\Psr18AttachmentsMiddleware\Exception\SoapMessageNotFoundException;

final class SoapMessageNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_can_throw_from_multipart_context(): void
    {
        $exception = SoapMessageNotFoundException::insideMultipart('soapmessage', 'application/soap+xml');

        $this->expectException(SoapMessageNotFoundException::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Soap message with id "soapmessage" and type "application/soap+xml" can not be found inside multipart response.');
        throw $exception;
    }

}
