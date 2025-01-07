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

If you are using MTOM attachments in combination with XOP you can use the `XopIncludeEncoder` to work directly with attachments from within your SOAP objects.
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

This will allow you to use attachments directly from within your SOAP request and responses:

```php
use Phpro\ResourceStream\Factory\FileStream;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;

// Your request can now contain Attachments directly:
// These attachments will be automatically added to the AttachmentStorageInterface and a <xop:Include> element will be added to your request instead.
$yourSoapPayload = (object) [
    'file' => Attachment::create(
        name: 'file',
        filename: 'your.pdf',
        content: FileStream::create('path/to/your.pdf', FileStream::READ_MODE)
    )
];

// If your resonse contains an <xop:Include> element, the AttachmentStorageInterface will automatically fetch the attachment and replace the <xop:Include> element with the actual attachment content:
$response = $yourSoapClient->request('Foo', $yourSoapPayload);
$response->foo->file->copyTo(FileStream::create('path/to/your.pdf', FileStream::WRITE_MODE));

```
