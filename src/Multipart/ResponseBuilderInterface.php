<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Multipart;

use Psr\Http\Message\ResponseInterface;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;

interface ResponseBuilderInterface
{
    public function __invoke(
        ResponseInterface $response,
        AttachmentStorageInterface $attachmentStorage,
        AttachmentType $attachmentType,
    ): ResponseInterface;
}
