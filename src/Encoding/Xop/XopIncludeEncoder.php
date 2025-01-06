<?php

namespace Soap\Psr18AttachmentMiddleware\Encoding\Xop;

use Soap\Encoding\Encoder\Context;
use Soap\Encoding\Encoder\XmlEncoder;
use Soap\Encoding\Xml\Node\Element;
use Soap\Psr18AttachmentMiddleware\Attachment\Attachment;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorage;
use VeeWee\Reflecta\Iso\Iso;
use VeeWee\Xml\Writer\Writer;
use function VeeWee\Xml\Writer\Builder\attribute;
use function VeeWee\Xml\Writer\Builder\namespaced_element;
use function VeeWee\Xml\Writer\Mapper\memory_output;

/**
 * @template-implements XmlEncoder<Attachment, non-empty-string>
 */
final class XopIncludeEncoder implements XmlEncoder
{
    public const XMLNS_XOP = 'http://www.w3.org/2004/08/xop/include';

    public function __construct(
        private readonly AttachmentStorage $attachmentStorage
    ) {
    }

    /**
     * @param Context $context
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
             * @return Attachment
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
