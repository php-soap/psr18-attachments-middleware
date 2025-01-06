<?php

namespace Soap\Psr18AttachmentMiddleware\Middleware;

use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Soap\Psr18AttachmentMiddleware\Multipart\RequestBuilder;
use Soap\Psr18AttachmentMiddleware\Multipart\ResponseBuilder;
use Soap\Psr18AttachmentMiddleware\Multipart\TransportType;
use Soap\Psr18AttachmentMiddleware\Storage\AttachmentStorage;

final class AttachmentsMiddleware implements Plugin
{
    private readonly RequestBuilder $requestBuilder;
    private readonly ResponseBuilder $responseBuilder;

    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly TransportType $transportType,
        ?RequestBuilder $requestBuilder = null,
        ?ResponseBuilder $responseBuilder = null,
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

                return ($this->responseBuilder)($response, $this->storage);
            }
        );
    }
}
