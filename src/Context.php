<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer;

use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Schema\Concerns\HasListeners;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

class Context
{
    use HasMeta;
    use HasListeners;

    private $api;
    private $request;

    public function __construct(JsonApi $api, ServerRequestInterface $request)
    {
        $this->api = $api;
        $this->request = $request;
    }

    /**
     * Get the JsonApi instance.
     */
    public function getApi(): JsonApi
    {
        return $this->api;
    }

    /**
     * Get the PSR-7 request instance.
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function withRequest(ServerRequestInterface $request): Context
    {
        return new static($this->api, $request);
    }

    /**
     * Get the request path relative to the API's base path.
     */
    public function getPath(): string
    {
        return $this->api->stripBasePath(
            $this->request->getUri()->getPath()
        );
    }

    public function response(callable $callback): void
    {
        $this->listeners['response'][] = $callback;
    }

    /**
     * Determine whether a field has been requested in a sparse fieldset.
     */
    public function fieldRequested(string $type, string $field, bool $default = true): bool
    {
        $queryParams = $this->request->getQueryParams();

        if (! isset($queryParams['fields'][$type])) {
            return $default;
        }

        return in_array($field, explode(',', $queryParams['fields'][$type]));
    }

    /**
     * Get the value of a filter.
     */
    public function filter(string $name): ?string
    {
        return $this->request->getQueryParams()['filter'][$name] ?? null;
    }

    /**
     * Get parsed JsonApi payload
     */
    public function getBody(): ?array
    {
        return $this->request->getParsedBody() ?: json_decode($this->request->getBody()->getContents(), true);
    }
}
