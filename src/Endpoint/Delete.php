<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\run_callbacks;

class Delete implements RequestHandlerInterface
{
    private $api;
    private $resource;
    private $model;

    public function __construct(JsonApi $api, ResourceType $resource, $model)
    {
        $this->api = $api;
        $this->resource = $resource;
        $this->model = $model;
    }

    /**
     * Handle a request to delete a resource.
     *
     * @throws ForbiddenException if the resource is not deletable.
     */
    public function handle(Request $request): ResponseInterface
    {
        $schema = $this->resource->getSchema();

        if (! evaluate($schema->isDeletable(), [$this->model, $request])) {
            throw new ForbiddenException;
        }

        run_callbacks($schema->getListeners('deleting'), [$this->model, $request]);

        if ($deleteCallback = $schema->getDeleteCallback()) {
            $deleteCallback($this->model, $request);
        } else {
            $this->resource->getAdapter()->delete($this->model);
        }

        run_callbacks($schema->getListeners('deleted'), [$this->model, $request]);

        return new Response(204);
    }
}
