<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Storage;

use Soap\Psr18AttachmentsMiddleware\Attachment\AttachmentsCollection;

interface AttachmentStorageInterface
{
    /**
     * List all attachments for current request
     *
     */
    public function requestAttachments(): AttachmentsCollection;

    /**
     * List all attachments available in current response
     *
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
