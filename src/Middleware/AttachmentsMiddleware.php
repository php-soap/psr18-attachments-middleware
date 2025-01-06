<?php declare(strict_types=1);

namespace Soap\Psr18AttachmentMiddleware\Middleware;

use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Soap\Psr18AttachmentMiddleware\Multipart\AttachmentType;
use Soap\Psr18AttachmentMiddleware\Multipart\RequestBuilder;
use Soap\Psr18AttachmentMiddleware\Multipart\RequestBuilderInterface;
use Soap\Psr18AttachmentMiddleware\Multipart\ResponseBuilder;
use Soap\Psr18AttachmentMiddleware\Multipart\ResponseBuilderInterface;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorageInterface;

final readonly class AttachmentsMiddleware implements Plugin
{
    private RequestBuilderInterface $requestBuilder;
    private ResponseBuilderInterface $responseBuilder;

    public function __construct(
        private AttachmentStorageInterface $storage,
        private AttachmentType $transportType,
        ?RequestBuilderInterface $requestBuilder = null,
        ?ResponseBuilderInterface $responseBuilder = null,
    ) {
        $this->requestBuilder = $requestBuilder ?? RequestBuilder::default();
        $this->responseBuilder = $responseBuilder ?? ResponseBuilder::default();
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $promise = $next(($this->requestBuilder)($request, $this->storage, $this->transportType));
        $this->storage->resetRequestAttachments();

        return $promise->then(
            function (ResponseInterface $response): ResponseInterface {
                $this->storage->resetResponseAttachments();

                return ($this->responseBuilder)($response, $this->storage, $this->transportType);
            }
        );
    }
}
