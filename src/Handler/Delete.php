<?php

namespace Tobyz\JsonApiServer\Handler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use function Tobyz\JsonApiServer\evaluate;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\ResourceType;
use function Tobyz\JsonApiServer\run_callbacks;
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

        if (! evaluate($schema->getDeletable(), [$this->model, $request])) {
            throw new ForbiddenException;
        }

        run_callbacks($schema->getListeners('deleting'), [$this->model, $request]);

        if ($deleter = $this->resource->getSchema()->getDelete()) {
            $deleter($this->model, $request);
        } else {
            $this->resource->getAdapter()->delete($this->model);
        }

        run_callbacks($schema->getListeners('deleted'), [$this->model, $request]);

        return new EmptyResponse;
    }
}
