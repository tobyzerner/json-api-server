<?php

namespace Tobyz\JsonApiServer\Handler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\ResourceType;
use Zend\Diactoros\Response\EmptyResponse;

class Delete implements RequestHandlerInterface
{
    private $resource;
    private $model;

    public function __construct(ResourceType $resource, $model)
    {
        $this->resource = $resource;
        $this->model = $model;
    }

    public function handle(Request $request): Response
    {
        $schema = $this->resource->getSchema();

        if (! ($schema->isDeletable)($request, $this->model)) {
            throw new ForbiddenException('You cannot delete this resource');
        }

        foreach ($schema->deletingCallbacks as $callback) {
            $callback($request, $this->model);
        }

        $this->resource->getAdapter()->delete($this->model);

        foreach ($schema->deletedCallbacks as $callback) {
            $callback($request, $this->model);
        }

        return new EmptyResponse;
    }
}
