<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Encoding\Xop;

use Soap\Encoding\Encoder\Context;
use Soap\Encoding\Encoder\XmlEncoder;
use Soap\Encoding\Xml\Node\Element;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;
use VeeWee\Reflecta\Iso\Iso;
use VeeWee\Xml\Writer\Writer;
use function Psl\Regex\replace;
use function Psl\Type\non_empty_string;
use function VeeWee\Xml\Writer\Builder\attribute;
use function VeeWee\Xml\Writer\Builder\namespaced_element;
use function VeeWee\Xml\Writer\Mapper\memory_output;

/**
 * @template-implements XmlEncoder<Attachment, non-empty-string>
 */
final readonly class XopIncludeEncoder implements XmlEncoder
{
    public const XMLNS_XOP = 'http://www.w3.org/2004/08/xop/include';

    public function __construct(
        private AttachmentStorageInterface $attachmentStorage
    ) {
    }

    /**
     * Encodes an attachment to a <xop:Include> element based on the XOP specification:
     *
     * @see https://www.w3.org/TR/xop10/#RFC2392
     *
     * @return Iso<Attachment, non-empty-string>
     */
    public function iso(Context $context): Iso
    {
        $cid = $this->cid();

        return new Iso(
            /**
             * @return non-empty-string
             */
            static function (Attachment $raw) use ($cid): string {
                /** @var non-empty-string */
                return Writer::inMemory()
                    ->write(namespaced_element(
                        self::XMLNS_XOP,
                        'xop',
                        'Include',
                        attribute('href', $cid->to($raw))
                    ))
                    ->map(memory_output());
            },
            /**
             * @param non-empty-string|Element $xml
             */
            static function (Element|string $xml) use ($cid) : Attachment {
                $element = ($xml instanceof Element ? $xml : Element::fromString($xml))->element();
                $href = $element->getAttribute('href');

                return $cid->from(non_empty_string()->assert($href));
            }
        );
    }

    /**
     * Encodes the cid href for the attachment based on the cid specification:
     *
     * @see https://www.ietf.org/rfc/rfc2392.txt
     *
     * @return Iso<Attachment, non-empty-string>
     */
    private function cid(): Iso
    {
        /** @var Iso<Attachment, non-empty-string> */
        return new Iso(
            /**
             * @return non-empty-string
             */
            function (Attachment $raw): string {
                $this->attachmentStorage->requestAttachments()->add($raw);

                return 'cid:' . replace($raw->id, '/^<(.*)>$/', '$1');
            },
            /**
             * @param non-empty-string $xml
             */
            function (string $xml): Attachment {
                $id = '<' . replace($xml, '/^cid:(.*)/', '$1') . '>';

                return $this->attachmentStorage->responseAttachments()->findById($id);
            }
        );
    }
}
