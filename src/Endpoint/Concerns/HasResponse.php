<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Header;
use Tobyz\JsonApiServer\SchemaContext;

trait HasResponse
{
    /**
     * @var Header[]
     */
    protected array $headers = [];

    /**
     * @var Closure[]
     */
    protected array $responseCallbacks = [];

    /**
     * Set custom headers for the response.
     *
     * @param Header[] $headers
     */
    public function headers(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Register a callback to customize the response.
     *
     * The callback receives the response and can return a modified or new response.
     */
    public function response(Closure $callback): static
    {
        $this->responseCallbacks[] = $callback;

        return $this;
    }

    /**
     * Apply custom headers to the response.
     */
    private function applyHeaders(ResponseInterface $response, Context $context): ResponseInterface
    {
        foreach ($this->headers as $header) {
            $headerContext = $context->withField($header);
            $value = $header->getValue($headerContext);

            if ($value !== null) {
                $value = $header->serializeValue($value, $headerContext);
                $response = $response->withHeader($header->name, (string) $value);
            }
        }

        return $response;
    }

    /**
     * Apply response callbacks in sequence.
     */
    private function applyResponseCallbacks(
        ResponseInterface $response,
        Context $context,
    ): ResponseInterface {
        foreach ($this->responseCallbacks as $callback) {
            $result = isset($context->model)
                ? $callback($response, $context->model, $context)
                : $callback($response, $context);

            if ($result instanceof ResponseInterface) {
                $response = $result;
            }
        }

        return $response;
    }

    /**
     * Apply response hooks (headers and callbacks).
     */
    private function applyResponseHooks(
        ResponseInterface $response,
        Context $context,
    ): ResponseInterface {
        $response = $this->applyHeaders($response, $context);
        $response = $this->applyResponseCallbacks($response, $context);

        return $response;
    }

    private function createResponse(array $document, Context $context): ResponseInterface
    {
        return $this->applyResponseHooks($context->createResponse($document), $context);
    }

    /**
     * Get OpenAPI headers schema for custom headers.
     */
    private function headersSchema(SchemaContext $context): array
    {
        $headers = [];

        foreach ($this->headers as $header) {
            $headers[$header->name] = ['schema' => $header->getSchema($context)];
        }

        return $headers;
    }

    private function responseSchema($content, SchemaContext $context): array
    {
        $response = [];

        if ($headers = $this->headersSchema($context)) {
            $response['headers'] = $headers;
        }

        if ($content) {
            $response['content'] = [
                JsonApi::MEDIA_TYPE => [
                    'schema' => $content,
                ],
            ];
        }

        return $response;
    }
}
