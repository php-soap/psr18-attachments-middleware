<?php

namespace Soap\Psr18AttachmentMiddleware\Attachment;

use Soap\Psr18AttachmentMiddleware\Exception\AttachmentNotFoundException;
use Traversable;

/**
 * @template-implements \IteratorAggregate<int, Attachment>
 */
final class AttachmentsCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<Attachment>
     */
    private array $attachments;

    /**
     * @no-named-arguments
     */
    public function __construct(Attachment ... $attachments)
    {
        $this->attachments = $attachments;
    }

    public function getIterator()
    {
        yield from $this->attachments;
    }

    public function count(): int
    {
        return count($this->attachments);
    }

    public function add(Attachment $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    public function findById(string $id): Attachment
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment->id === $id) {
                return $attachment;
            }
        }

        throw AttachmentNotFoundException::withId($id);
    }
}
