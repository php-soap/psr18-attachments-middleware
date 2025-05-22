<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Multipart;

use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Multipart\AttachmentType;
use Soap\Psr18AttachmentsMiddleware\Multipart\ResponseBuilder;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;

final class ResponseBuilderTest extends TestCase
{
    #[Test]
    public function it_does_nothing_on_non_multipart_response(): void
    {
        $attachmentStorage = self::createAttachmentsStore();
        $responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $response = $responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/xml')
            ->withBody($streamFactory->createStream('<xml></xml>'));

        $responseBuilder = ResponseBuilder::default();
        $actual = $responseBuilder($response, $attachmentStorage, AttachmentType::Swa);

        static::assertSame($response, $actual);
    }

    #[Test]
    public function it_can_parse_swa_related_multipart(): void
    {
        $attachmentStorage = self::createAttachmentsStore();
        $responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $boundary = '4acabd8e751e40993fe016a494eded6';

        $multipartResponse = $responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="soaprequest"')
            ->withBody($streamFactory->createStream(
                <<<EORESPONSE
                --{$boundary}
                Content-Type: text/xml; charset=UTF-8
                Content-ID: soaprequest
                
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
                EORESPONSE
            ));

        $responseBuilder = ResponseBuilder::default();
        $actual = $responseBuilder($multipartResponse, $attachmentStorage, AttachmentType::Swa);

        static::assertSame($multipartResponse->getStatusCode(), $actual->getStatusCode());
        static::assertSame(
            <<<EOXML
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
            </SOAP-ENV:Envelope>
            EOXML,
            (string) $actual->getBody()
        );
        self::assertResponseAttachments($attachmentStorage);
    }

    #[Test]
    public function it_can_parse_mtom_related_multipart_with_cid_compliance(): void
    {
        $attachmentStorage = self::createAttachmentsStore();
        $responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $boundary = '4acabd8e751e40993fe016a494eded6';

        $multipartResponse = $responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="soaprequest"')
            ->withBody($streamFactory->createStream(
                <<<EORESPONSE
                --{$boundary}
                Content-Type: application/xop+xml; charset=UTF-8; type=application/soap+xml
                Content-ID: soaprequest
                
                <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                    <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
                </SOAP-ENV:Envelope>
                --{$boundary}
                Content-ID: <attachment1@domain.com>
                Content-Type: text/plain
                Content-Disposition: attachment; name="file1"; filename="attachment1.txt"
                Content-Transfer-Encoding: binary
                
                attachment1
                --{$boundary}
                Content-ID: <attachment2@domain.com>
                Content-Type: text/plain
                Content-Disposition: attachment; name="file2"; filename="attachment2.txt"
                Content-Transfer-Encoding: binary
                
                attachment2
                --{$boundary}--

                EORESPONSE
            ));

        $responseBuilder = ResponseBuilder::default();
        $actual = $responseBuilder($multipartResponse, $attachmentStorage, AttachmentType::Mtom);

        static::assertSame($multipartResponse->getStatusCode(), $actual->getStatusCode());
        static::assertSame(
            <<<EOXML
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
            </SOAP-ENV:Envelope>
            EOXML,
            (string) $actual->getBody()
        );
        self::assertResponseAttachments($attachmentStorage, ['<attachment1@domain.com>', '<attachment2@domain.com>']);
    }

    #[Test]
    public function it_can_parse_multipart_without_start_parameter(): void
    {
        $attachmentStorage = self::createAttachmentsStore();
        $responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $boundary = '4acabd8e751e40993fe016a494eded6';

        $multipartResponse = $responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'multipart/related; type="application/xop+xml"; boundary="' . $boundary. '"; start-info="text/xml"')
            ->withBody($streamFactory->createStream(
                <<<EORESPONSE
                --{$boundary}
                Content-Type: application/xop+xml; charset=UTF-8; type="text/xml"
                
                <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                    <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
                </SOAP-ENV:Envelope>
                --{$boundary}
                Content-ID: <attachment1@domain.com>
                Content-Type: text/plain
                Content-Disposition: attachment; name="file1"; filename="attachment1.txt"
                Content-Transfer-Encoding: binary
                
                attachment1
                --{$boundary}
                Content-ID: <attachment2@domain.com>
                Content-Type: text/plain
                Content-Disposition: attachment; name="file2"; filename="attachment2.txt"
                Content-Transfer-Encoding: binary
                
                attachment2
                --{$boundary}--

                EORESPONSE
            ));

        $responseBuilder = ResponseBuilder::default();
        $actual = $responseBuilder($multipartResponse, $attachmentStorage, AttachmentType::Mtom);

        static::assertSame($multipartResponse->getStatusCode(), $actual->getStatusCode());
        static::assertSame(
            <<<EOXML
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
            </SOAP-ENV:Envelope>
            EOXML,
            (string) $actual->getBody()
        );
        self::assertResponseAttachments($attachmentStorage, ['<attachment1@domain.com>', '<attachment2@domain.com>']);
    }

    private static function assertResponseAttachments(
        AttachmentStorage $storage,
        array $expectedIds = ['attachment1', 'attachment2']
    ): void {
        $responseAttachments = [...$storage->responseAttachments()];
        static::assertCount(2, $responseAttachments);

        static::assertSame($expectedIds[0], $responseAttachments[0]->id);
        static::assertSame('file1', $responseAttachments[0]->name);
        static::assertSame('attachment1.txt', $responseAttachments[0]->filename);
        static::assertSame('text/plain', $responseAttachments[0]->mimeType);
        static::assertSame('attachment1', $responseAttachments[0]->content->getContents());

        static::assertSame($expectedIds[1], $responseAttachments[1]->id);
        static::assertSame('file2', $responseAttachments[1]->name);
        static::assertSame('attachment2.txt', $responseAttachments[1]->filename);
        static::assertSame('text/plain', $responseAttachments[1]->mimeType);
        static::assertSame('attachment2', $responseAttachments[1]->content->getContents());
    }

    private static function createAttachmentsStore(): AttachmentStorage
    {
        return new AttachmentStorage();
    }
}
