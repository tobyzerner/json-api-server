<?php

namespace Tobscure\JsonApiServer\Handler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobscure\JsonApiServer\Api;
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
        $adapter = $this->resource->getAdapter();

        $this->save($this->model, $request);

        return (new Show($this->api, $this->resource, $this->model))->handle($request);
    }
}
