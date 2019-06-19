<?php

namespace Tobscure\JsonApiServer\Handler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobscure\JsonApiServer\Api;
use Tobscure\JsonApiServer\Exception\ForbiddenException;
use Tobscure\JsonApiServer\ResourceType;

class Update implements RequestHandlerInterface
{
    use Concerns\SavesData;

    private $api;
    private $resource;
    private $model;

    public function __construct(Api $api, ResourceType $resource, $model)
    {
        $this->api = $api;
        $this->resource = $resource;
        $this->model = $model;
    }

    public function handle(Request $request): Response
    {
        $schema = $this->resource->getSchema();

        if (! ($schema->isUpdatable)($request, $this->model)) {
            throw new ForbiddenException('You cannot update this resource');
        }

        foreach ($schema->updatingCallbacks as $callback) {
            $callback($request, $this->model);
        }

        $this->save($this->model, $request);

        foreach ($schema->updatedCallbacks as $callback) {
            $callback($request, $this->model);
        }

        return (new Show($this->api, $this->resource, $this->model))->handle($request);
    }
}
