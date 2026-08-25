<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Attachment;

use Countable;
use IteratorAggregate;
use Soap\Psr18AttachmentsMiddleware\Exception\AttachmentNotFoundException;
use Traversable;

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

    public function getIterator(): Traversable
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

    /**
     * @param callable(Attachment): bool $predicate
     */
    public function find(callable $predicate): ?Attachment
    {
        foreach ($this->attachments as $attachment) {
            if ($predicate($attachment)) {
                return $attachment;
            }
        }

        return null;
    }

    /**
     * Matches on id alone, so the caller owes it the same file: `Attachment::withContent()` is what
     * guarantees that. Handing over a different file under a taken id silently changes what the
     * collection says that id addresses.
     */
    public function replace(Attachment $attachment): self
    {
        foreach ($this->attachments as $index => $current) {
            if ($current->id === $attachment->id) {
                $this->attachments[$index] = $attachment;

                return $this;
            }
        }

        throw AttachmentNotFoundException::withId($attachment->id);
    }
}
