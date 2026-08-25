<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Exception;

use Soap\Engine\Exception\RuntimeException;

final class InvalidHeaderValueException extends RuntimeException
{
    /**
     * A line break in a header value does not produce a broken header, it produces extra ones: everything
     * after it reads as a header of its own. A Content-Transfer-Encoding forged that way lands ahead of the
     * real one and changes how a receiver decodes the file.
     */
    public static function controlCharacter(string $parameter): self
    {
        return new self(sprintf(
            'The attachment\'s "%s" carries a control character, which cannot travel in a MIME header.',
            $parameter
        ));
    }
}
