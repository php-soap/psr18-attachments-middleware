<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Riverline\MultiPartParser\StreamedPart;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Multipart\AttachmentType;
use Soap\Psr18AttachmentsMiddleware\Multipart\RequestBuilder;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;

final class RequestBuilderTest extends TestCase
{
    #[Test]
    public function it_returns_regular_request_on_no_attachments(): void
    {
        $storage = new AttachmentStorage();
        $request = self::createSoapRequest();
        $builder = RequestBuilder::default();

        $multipartRequest = $builder($request, $storage, AttachmentType::Swa);

        static::assertSame($request, $multipartRequest);
    }

    #[Test]
    public function it_can_build_swa_related_multipart(): void
    {
        $storage = $this->createAttachmentsStore();
        $requestBuilder = RequestBuilder::default();
        $request = self::createSoapRequest()
            ->withAddedHeader('SoapAction', $soapAction = 'http://example.com')
            ->withAddedHeader('Content-Type', 'application/xml');

        $multipartRequest = $requestBuilder($request, $storage, AttachmentType::Swa);
        $contentType = $multipartRequest->getHeaderLine('Content-Type');
        $boundary = StreamedPart::getHeaderOption($contentType, 'boundary');

        $expectedPayload = <<<EOF
        --{$boundary}
        Content-Type: text/xml; charset=UTF-8
        Content-ID: <soaprequest@main>
        
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
            <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
        </SOAP-ENV:Envelope>
        --{$boundary}
        Content-ID: attachment1
        Content-Type: text/plain
        Content-Disposition: attachment; name="file1"; filename="attachment1.txt"
        Content-Transfer-Encoding: binary
        
        attachment1
        --{$boundary}
        Content-ID: attachment2
        Content-Type: text/plain
        Content-Disposition: attachment; name="file2"; filename="attachment2.txt"
        Content-Transfer-Encoding: binary
        
        attachment2
        --{$boundary}--

        EOF;

        static::assertSame($soapAction, $multipartRequest->getHeaderLine('SoapAction'));
        static::assertSame('multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="<soaprequest@main>"', $contentType);
        static::assertSame(self::crlf($expectedPayload), (string) $multipartRequest->getBody());
    }


    #[Test]
    public function it_can_build_mtom_related_multipart_with_explicit_soap_action_info(): void
    {
        $storage = $this->createAttachmentsStore();
        $requestBuilder = RequestBuilder::default();
        $request = self::createSoapRequest()
            ->withAddedHeader('Content-Type', 'application/soap+xml; action="foo"');

        $multipartRequest = $requestBuilder($request, $storage, AttachmentType::Mtom);
        $contentType = $multipartRequest->getHeaderLine('Content-Type');
        $boundary = StreamedPart::getHeaderOption($contentType, 'boundary');

        $expectedPayload = <<<EOF
        --{$boundary}
        Content-Type: application/xop+xml; charset=UTF-8; type="application/soap+xml; action=\"foo\""
        Content-ID: <soaprequest@main>
        
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
            <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
        </SOAP-ENV:Envelope>
        --{$boundary}
        Content-ID: attachment1
        Content-Type: text/plain
        Content-Disposition: attachment; name="file1"; filename="attachment1.txt"
        Content-Transfer-Encoding: binary
        
        attachment1
        --{$boundary}
        Content-ID: attachment2
        Content-Type: text/plain
        Content-Disposition: attachment; name="file2"; filename="attachment2.txt"
        Content-Transfer-Encoding: binary
        
        attachment2
        --{$boundary}--

        EOF;

        static::assertSame('multipart/related; type="application/xop+xml"; boundary="'.$boundary.'"; start="<soaprequest@main>"; start-info="application/soap+xml; action=\"foo\""', $contentType);
        static::assertSame(self::crlf($expectedPayload), (string) $multipartRequest->getBody());
    }

    #[Test]
    public function it_can_build_mtom_related_multipart_without_soap_action_info(): void
    {
        $storage = $this->createAttachmentsStore();
        $requestBuilder = RequestBuilder::default();
        $request = self::createSoapRequest()
            ->withAddedHeader('Content-Type', 'application/soap+xml');

        $multipartRequest = $requestBuilder($request, $storage, AttachmentType::Mtom);
        $contentType = $multipartRequest->getHeaderLine('Content-Type');
        $boundary = StreamedPart::getHeaderOption($contentType, 'boundary');

        $expectedPayload = <<<EOF
        --{$boundary}
        Content-Type: application/xop+xml; charset=UTF-8; type="application/soap+xml"
        Content-ID: <soaprequest@main>
        
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
            <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
        </SOAP-ENV:Envelope>
        --{$boundary}
        Content-ID: attachment1
        Content-Type: text/plain
        Content-Disposition: attachment; name="file1"; filename="attachment1.txt"
        Content-Transfer-Encoding: binary
        
        attachment1
        --{$boundary}
        Content-ID: attachment2
        Content-Type: text/plain
        Content-Disposition: attachment; name="file2"; filename="attachment2.txt"
        Content-Transfer-Encoding: binary
        
        attachment2
        --{$boundary}--

        EOF;

        static::assertSame('multipart/related; type="application/xop+xml"; boundary="'.$boundary.'"; start="<soaprequest@main>"; start-info="application/soap+xml"', $contentType);
        static::assertSame(self::crlf($expectedPayload), (string) $multipartRequest->getBody());
    }

    private static function createAttachmentsStore(): AttachmentStorage
    {
        $storage = new AttachmentStorage();
        $storage->requestAttachments()
            ->add(
                new Attachment(
                    'attachment1',
                    'file1',
                    'attachment1.txt',
                    'text/plain',
                    MemoryStream::create()->write('attachment1')
                )
            )
            ->add(
                new Attachment(
                    'attachment2',
                    'file2',
                    'attachment2.txt',
                    'text/plain',
                    MemoryStream::create()->write('attachment2')
                )
            );

        return $storage;
    }

    private static function createSoapRequest(): RequestInterface
    {
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        return $requestFactory->createRequest('POST', 'http://example.com')
            ->withBody(
                $streamFactory->createStream(
                    self::crlf(
                        <<<EOXML
                        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                            <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
                        </SOAP-ENV:Envelope>
                        EOXML
                    )
                )
            );
    }

    private static function crlf(string $string): string
    {
        return str_replace("\n", "\r\n", $string);
    }
}
