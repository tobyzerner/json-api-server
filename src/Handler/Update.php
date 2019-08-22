<?php

namespace Tobyz\JsonApiServer\Handler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use function Tobyz\JsonApiServer\evaluate;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\ResourceType;
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

    public function handle(Request $request): Response
    {
        $schema = $this->resource->getSchema();

        if (! evaluate($schema->getUpdatable(), [$this->model, $request])) {
            throw new ForbiddenException;
        }

        $data = $this->parseData($request->getParsedBody());

        $this->validateFields($data, $this->model, $request);
        $this->loadRelatedResources($data, $request);
        $this->assertDataValid($data, $this->model, $request, false);
        $this->setValues($data, $this->model, $request);

        run_callbacks($schema->getListeners('updating'), [$this->model, $request]);

        $this->save($data, $this->model, $request);

        run_callbacks($schema->getListeners('updated'), [$this->model, $request]);

        return (new Show($this->api, $this->resource, $this->model))->handle($request);
    }
}
