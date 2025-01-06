<?php

namespace Soap\Psr18AttachmentMiddleware\Storage;

use Soap\Psr18AttachmentMiddleware\Attachment\AttachmentsCollection;

interface AttachmentStorage
{
    /**
     * List all attachments for current request
     *
     * @return AttachmentsCollection
     */
    public function requestAttachments(): AttachmentsCollection;

    /**
     * List all attachments available in current response
     *
     * @return AttachmentsCollection
     */
    public function responseAttachments(): AttachmentsCollection;


    /**
     * Will be used by the middleware the reset the request attachments in between requests so that the same storage instance can be used.
     */
    public function resetRequestAttachments(): void;

    /**
     * Will be used by the middleware the reset the request attachments in between requests so that the same storage instance can be used.
     */
    public function resetResponseAttachments(): void;
}
