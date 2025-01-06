<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentMiddleware\Multipart;

use Psr\Http\Message\RequestInterface;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorageInterface;

interface RequestBuilderInterface
{
    public function __invoke(
        RequestInterface $request,
        AttachmentStorageInterface $attachmentStorage,
        AttachmentType $attachmentType,
    ): RequestInterface;
}
