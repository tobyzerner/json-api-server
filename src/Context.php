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

    private $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function response(callable $callback): void
    {
        $this->listeners['response'][] = $callback;
    }

    public function fieldRequested(string $type, string $field, bool $default = true): bool
    {
        $queryParams = $this->request->getQueryParams();

        if (! isset($queryParams['fields'][$type])) {
            return $default;
        }

        return in_array($field, explode(',', $queryParams['fields'][$type]));
    }
}
