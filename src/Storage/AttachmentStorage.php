<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Storage;

use Soap\Psr18AttachmentsMiddleware\Attachment\AttachmentsCollection;

final class AttachmentStorage implements AttachmentStorageInterface
{
    private AttachmentsCollection $requestAttachments;
    private AttachmentsCollection $responseAttachments;

    public function __construct()
    {
        $this->requestAttachments = new AttachmentsCollection();
        $this->responseAttachments = new AttachmentsCollection();
    }

    public function requestAttachments(): AttachmentsCollection
    {
        return $this->requestAttachments;
    }

    public function responseAttachments(): AttachmentsCollection
    {
        return $this->responseAttachments;
    }

    public function resetRequestAttachments(): void
    {
        $this->requestAttachments = new AttachmentsCollection();
    }

    public function resetResponseAttachments(): void
    {
        $this->responseAttachments = new AttachmentsCollection();
    }
}
