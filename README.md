# SOAP SWA / MTOM Middleware

This package provides the tools you need in order to add [SWA](https://www.w3.org/TR/SOAP-attachments/) or [MTOM](https://www.w3.org/TR/soap12-mtom/) Attachments to your PSR-18 based SOAP Transport.

# Want to help out? 💚

- [Become a Sponsor](https://github.com/php-soap/.github/blob/main/HELPING_OUT.md#sponsor)
- [Let us do your implementation](https://github.com/php-soap/.github/blob/main/HELPING_OUT.md#let-us-do-your-implementation)
- [Contribute](https://github.com/php-soap/.github/blob/main/HELPING_OUT.md#contribute)
- [Help maintain these packages](https://github.com/php-soap/.github/blob/main/HELPING_OUT.md#maintain)

Want more information about the future of this project? Check out this list of the [next big projects](https://github.com/php-soap/.github/blob/main/PROJECTS.md) we'll be working on.

# Installation

```shell
composer require php-soap/psr18-attachments-middleware
```

This package includes the [php-soap/psr18-transport](https://github.com/php-soap/psr18-transport/) package and is meant to be used together with it.

## Usage

### Attachments middleware

This middleware is used to add attachments to your SOAP request:

```php
use Http\Client\Common\PluginClient;
use Soap\Psr18Transport\Psr18Transport;
use Soap\Psr18AttachmentsMiddleware\Middleware\AttachmentsMiddleware;
use Soap\Psr18AttachmentsMiddleware\Multipart\AttachmentType;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;

// You should store this attachment storage in a central place in your application e.g. inside a service container.
// It is used to store the attachments that are being sent and received.
$attachmentsStorage = new AttachmentStorage();

$transport = Psr18Transport::createForClient(
    new PluginClient($yourPsr18Client, [
        new AttachmentsMiddleware(
            $attachmentsStorage,
            AttachmentType::Swa // or AttachmentType::Mtom
        ),
    ])
);
```

This middleware will convert your regular SOAP request into a multipart SOAP request that contains the request attachments.
A response that contains attachments will be converted back into a regular SOAP response whilst storing a copy of the attachments.

### Adding attachments

Adding attachments to your request is done by using the `AttachmentsStorage` before sending your request to the SOAP server:

```php
use Http\Client\Common\PluginClient;
use Phpro\ResourceStream\Factory\FileStream;
use Soap\Psr18Transport\Psr18Transport;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;

// You should store this attachment storage in a central place in your application.
// It is used to store the attachments that are being sent and received.
$attachmentsStorage = new AttachmentStorage();

$attachmentsStorage->requestAttachments()->add(
    Attachment::create(
        name: 'file',
        filename: 'your.pdf',
        content: FileStream::create('path/to/your.pdf', FileStream::READ_MODE),
    )
);
$yourSoapClient->request('Foo', $soapPayload);
```

### The headers an attachment travels with

An `Attachment` holds four facts about a file, and `headers()` is how those facts are spelled as MIME:

```php
$attachment = Attachment::cid('invoice@example.com', 'invoice', 'invoice.pdf', $stream);

$attachment->headers();
// Content-ID: <invoice@example.com>
// Content-Type: application/pdf
// Content-Disposition: attachment; name="invoice"; filename="invoice.pdf"
```

`RequestBuilder` puts that set on the wire as it stands, adding only a `Content-Transfer-Encoding: binary`
when you did not supply one.

Pass extra headers when you need something the four facts cannot say. One naming a fact the attachment
already describes stands in for that header rather than being added beside it, so the set never says a thing
twice:

```php
use Psl\MIME\Headers;

new Attachment(
    '<invoice@example.com>', 'invoice', 'invoice.xml', 'application/xml', $stream,
    Headers::fromPairs([
        ['Content-Type', 'application/xml; charset=UTF-8'],   // stands in for the described one
        ['Content-Location', 'http://example.com/invoice.xml'], // appended
    ]),
);
```

The one header an extra cannot restate is `Content-ID`. That is the part's identity, and an
`AttachmentsCollection` looks an attachment up by it, so a set that re-addressed the part on the wire would
send a file under a name nothing here answers to.

`Attachment::fromHeaders()` goes the other way, which is how `ResponseBuilder` builds an attachment out of
what actually arrived: the facts are read out of the set and the set travels on untouched. A `charset` you
were sent therefore survives the round trip, while `mimeType` stays the media type's essence.

```php
$attachment = Attachment::fromHeaders($headers, $stream);

$attachment->mimeType;                       // 'application/xml'
$attachment->headers()->get('Content-Type'); // 'application/xml; charset=UTF-8'
```

`AttachmentHeaders` is the translation itself, if you need it directly. Its readers answer `null` rather than
guessing, and a header they cannot read still travels on: unreadable to us is not unreadable to the peer that
wrote it.

`withHeaders()` gives you the same file in another envelope, leaving its identity and its bytes alone.
`withContent()` gives you the same file in another representation, and drops the extras, since they described
the bytes being replaced.

### Receiving attachments

Receiving attachments is done by using the `AttachmentsStorage` after receiving your response from the SOAP server:

```php
use Http\Client\Common\PluginClient;
use Phpro\ResourceStream\Factory\FileStream;
use Soap\Psr18Transport\Psr18Transport;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorage;

// You should store this attachment storage in a central place in your application.
// It is used to store the attachments that are being sent and received.
$attachmentsStorage = new AttachmentStorage();

$soapResponse = $yourSoapClient->request('Foo', $soapPayload);
$attachments = $attachmentsStorage->responseAttachments()

foreach ($attachments as $attachment) {
    $attachment->content->copyTo(
        FileStream::create('path/to/your/'.$attachment->filename, FileStream::WRITE_MODE)
    );
}
```

## Encoders

### XOP Includes

If you are using MTOM attachments in combination with [XOP](https://www.w3.org/TR/xop10/) you can use the `XopIncludeEncoder` to work directly with attachments from within your SOAP objects.
This requires you to use the [php-soap/encoder](https://github.com/php-soap/encoder) pacakge:

```sh
composer require php-soap/encoder
```

```php
use Soap\Encoding\EncoderRegistry;
use Soap\Psr18AttachmentsMiddleware\Encoding\Xop\XopIncludeEncoder

// You should store this attachment storage in a central place in your application.
// It is used to store the attachments that are being sent and received.
$attachmentsStorage = new AttachmentStorage();

EncoderRegistry::default()
    ->addComplexTypeConverter(XopIncludeEncoder::XMLNS_XOP, 'Include', new XopIncludeEncoder($attachmentsStorage));
```

This will allow you to use attachments directly from within your SOAP request and responses without the need of adding them to the `AttachmentStorage` manually:

```php
use Phpro\ResourceStream\Factory\FileStream;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;

// Your request can now contain Attachments directly:
// These attachments will be automatically added to the AttachmentStorageInterface and a <xop:Include> element will be added to your request instead.
$yourSoapPayload = (object) [
    // A special cid named constructor is added to make sure your attachment Content-Id is cid spec-compliant and therefore can be used with XOP.
    'file' => Attachment::cid(
        uri: 'foo@domain.com',
        filename: 'your.pdf',
        content: FileStream::create('path/to/your.pdf', FileStream::READ_MODE)
    )
];

// If your resonse contains an <xop:Include> element, the AttachmentStorageInterface will automatically fetch the attachment and replace the <xop:Include> element with the actual attachment content:
$response = $yourSoapClient->request('Foo', $yourSoapPayload);
$response->foo->file->copyTo(FileStream::create('path/to/your.pdf', FileStream::WRITE_MODE));

```
