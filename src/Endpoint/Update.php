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

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Context;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\run_callbacks;

class Update
{
    use Concerns\SavesData;

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
     * @throws ForbiddenException if the resource is not updatable.
     */
    public function handle(Context $context): ResponseInterface
    {
        $schema = $this->resource->getSchema();

        if (! evaluate($schema->isUpdatable(), [$this->model, $context])) {
            throw new ForbiddenException;
        }

        $data = $this->parseData($context->getRequest()->getParsedBody(), $this->model);

        $this->validateFields($data, $this->model, $context);
        $this->loadRelatedResources($data, $context);
        $this->assertDataValid($data, $this->model, $context, false);
        $this->setValues($data, $this->model, $context);

        run_callbacks($schema->getListeners('updating'), [&$this->model, $context]);

        $this->save($data, $this->model, $context);

        run_callbacks($schema->getListeners('updated'), [&$this->model, $context]);

        $adapter = $this->resource->getAdapter();
        $freshModel = $this->findResource($this->resource, $adapter->getId($this->model), $context);

        return (new Show($this->api, $this->resource, $freshModel))
            ->handle($context);
    }
}
