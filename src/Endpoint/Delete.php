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
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Context;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\run_callbacks;

class Delete
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
     * @throws ForbiddenException if the resource is not deletable.
     */
    public function handle(Context $context): ResponseInterface
    {
        $schema = $this->resource->getSchema();

        if (! evaluate($schema->isDeletable(), [$this->model, $context])) {
            throw new ForbiddenException;
        }

        run_callbacks($schema->getListeners('deleting'), [&$this->model, $context]);

        if ($deleteCallback = $schema->getDeleteCallback()) {
            $deleteCallback($this->model, $context);
        } else {
            $this->resource->getAdapter()->delete($this->model);
        }

        run_callbacks($schema->getListeners('deleted'), [&$this->model, $context]);

        return new Response(204);
    }
}
