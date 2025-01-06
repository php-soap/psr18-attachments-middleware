<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Attachment;

use Countable;
use IteratorAggregate;
use Soap\Psr18AttachmentsMiddleware\Exception\AttachmentNotFoundException;

/**
 * @template-implements \IteratorAggregate<int, Attachment>
 */
final class AttachmentsCollection implements Countable, IteratorAggregate
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

    public function getIterator(): iterable
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
