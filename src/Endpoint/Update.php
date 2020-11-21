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

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\run_callbacks;

class Update implements RequestHandlerInterface
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
     * Handle a request to update a resource.
     *
     * @throws ForbiddenException if the resource is not updatable.
     */
    public function handle(Request $request): Response
    {
        $schema = $this->resource->getSchema();

        if (! evaluate($schema->isUpdatable(), [$this->model, $request])) {
            throw new ForbiddenException;
        }

        $data = $this->parseData($request->getParsedBody(), $this->model);

        $this->validateFields($data, $this->model, $request);
        $this->loadRelatedResources($data, $request);
        $this->assertDataValid($data, $this->model, $request, false);
        $this->setValues($data, $this->model, $request);

        run_callbacks($schema->getListeners('updating'), [$this->model, $request]);

        $this->save($data, $this->model, $request);

        run_callbacks($schema->getListeners('updated'), [$this->model, $request]);

        return (new Show($this->api, $this->resource, $this->model))
            ->handle($request);
    }
}
