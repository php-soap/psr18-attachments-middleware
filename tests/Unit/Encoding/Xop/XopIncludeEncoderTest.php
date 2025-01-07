<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Encoding\Xop;

use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Encoding\Encoder\Context;
use Soap\Encoding\EncoderRegistry;
use Soap\Engine\Metadata\Collection\MethodCollection;
use Soap\Engine\Metadata\Collection\TypeCollection;
use Soap\Engine\Metadata\InMemoryMetadata;
use Soap\Engine\Metadata\Model\XsdType;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Encoding\Xop\XopIncludeEncoder;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;
use Soap\WsdlReader\Model\Definitions\Namespaces;

final class XopIncludeEncoderTest extends TestCase
{
    #[Test]
    public function it_can_encode_xop_include_attachment(): void
    {
        $encoder = $this->createEncoder(
            $storage = $this->createStorage()
        );
        $iso = $encoder->iso($this->createContext());

        $result = $iso->to(
            $attachment = new Attachment('<foo@x.com>', 'file', 'file.pdf', 'application/pdf', MemoryStream::create())
        );

        static::assertSame('<xop:Include href="cid:foo@x.com" xmlns:xop="http://www.w3.org/2004/08/xop/include"/>', $result);
        static::assertSame($attachment, $storage->requestAttachments()->findById('<foo@x.com>'));
    }

    #[Test]
    public function it_can_decode_xop_include_attachment(): void
    {
        $encoder = $this->createEncoder(
            $storage = $this->createStorage()
        );
        $iso = $encoder->iso($this->createContext());

        $storage->responseAttachments()->add(
            $attachment = Attachment::cid('foo@x.com', 'file', 'file.pdf', MemoryStream::create())
        );
        $result = $iso->from('<xop:Include href="cid:foo@x.com" xmlns:xop="http://www.w3.org/2004/08/xop/include"/>');

        static::assertSame($attachment, $result);
    }

    private function createStorage(): AttachmentStorageInterface
    {
        return new AttachmentStorage();
    }

    private function createEncoder(AttachmentStorageInterface $storage): XopIncludeEncoder
    {
        return new XopIncludeEncoder($storage);
    }

    private function createContext(): Context
    {
        return new Context(
            XsdType::any(),
            new InMemoryMetadata(new TypeCollection(), new MethodCollection()),
            EncoderRegistry::default(),
            new Namespaces([], []),
        );
    }
}
