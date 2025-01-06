<?php declare(strict_types=1);

namespace SoapTest\Psr18AttachmentsMiddleware\Unit\Middleware;

use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Mock\Client;
use Phpro\ResourceStream\Factory\MemoryStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Middleware\AttachmentsMiddleware;
use Soap\Psr18AttachmentsMiddleware\Multipart\AttachmentType;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;

final class AttachmentsMiddlewareTest extends TestCase
{
    #[Test]
    public function it_can_attach_attachments(): void
    {
        $oldAttachment = Attachment::create('old', 'old-file.pdf', MemoryStream::create()->write('content'));
        $storage = new AttachmentStorage();
        $storage->responseAttachments()->add($oldAttachment);

        $client = new PluginClient($mockClient = new Client(), [
            new AttachmentsMiddleware($storage, AttachmentType::Swa)
        ]);
        $boundary = '4acabd8e751e40993fe016a494eded6';
        $mockClient->setDefaultResponse(
            Psr17FactoryDiscovery::findResponseFactory()
                ->createResponse(200)
                ->withAddedHeader('Content-Type', 'multipart/related; type="text/xml"; boundary="' . $boundary. '"; start="soaprequest"')
                ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream(
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
                    --{$boundary}--
                    
                    EORESPONSE
                ))
        );

        $storage->requestAttachments()->add(
            Attachment::create('file', 'file.pdf', MemoryStream::create()->write('content'))
        );
        $response = $client->sendRequest(
            Psr17FactoryDiscovery::findRequestFactory()
                ->createRequest('POST', 'http://example.com')
                ->withAddedHeader('SoapAction', 'http://example.com')
        );

        static::assertSame(
            <<<EOXML
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                <SOAP-ENV:Body xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>
            </SOAP-ENV:Envelope>
            EOXML,
            (string) $response->getBody()
        );
        $requestContentType = $mockClient->getLastRequest()->getHeaderLine('Content-Type');
        static::assertStringContainsString('multipart/related; type="text/xml";', $requestContentType);
        static::assertStringContainsString('start="soaprequest"', $requestContentType);
        static::assertCount(0, $storage->requestAttachments());
        static::assertCount(1, $storage->responseAttachments());
        static::assertSame(
            'attachment1',
            $storage->responseAttachments()->findById('attachment1')->content->getContents()
        );
    }
}
