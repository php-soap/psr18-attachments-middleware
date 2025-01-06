<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentsMiddleware\Encoding\Xop;

use Soap\Encoding\Encoder\Context;
use Soap\Encoding\Encoder\XmlEncoder;
use Soap\Encoding\Xml\Node\Element;
use Soap\Psr18AttachmentsMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentsMiddleware\Storage\AttachmentStorageInterface;
use VeeWee\Reflecta\Iso\Iso;
use VeeWee\Xml\Writer\Writer;
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
     * @return Iso<Attachment, non-empty-string>
     */
    public function iso(Context $context): Iso
    {
        return new Iso(
            /**
             * @return non-empty-string
             */
            function (Attachment $raw): string {

                $this->attachmentStorage->requestAttachments()->add($raw);

                /** @var non-empty-string */
                return Writer::inMemory()
                    ->write(namespaced_element(
                        self::XMLNS_XOP,
                        'xop',
                        'Include',
                        attribute('href', 'cid:' . $raw->id)
                    ))
                    ->map(memory_output());
            },
            /**
             * @param non-empty-string|Element $xml
             */
            function (Element|string $xml): Attachment {
                $element = ($xml instanceof Element ? $xml : Element::fromString($xml))->element();
                $href = $element->getAttribute('href');
                $id = preg_replace('/^cid:(.*)/', '$1', $href);

                return $this->attachmentStorage->responseAttachments()->findById($id);
            }
        );
    }
}
