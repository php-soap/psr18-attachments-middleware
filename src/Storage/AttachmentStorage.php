<?php

namespace Soap\Psr18AttachmentMiddleware\Storage;

use Soap\Psr18AttachmentMiddleware\Attachment\AttachmentsCollection;

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
