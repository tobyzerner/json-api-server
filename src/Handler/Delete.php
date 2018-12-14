<?php

namespace Tobscure\JsonApiServer\Handler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobscure\JsonApiServer\Exception\ForbiddenException;
use Tobscure\JsonApiServer\ResourceType;
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
        if (! ($this->resource->getSchema()->isDeletable)($this->model, $request)) {
            throw new ForbiddenException('You cannot delete this resource');
        }

        $this->resource->getAdapter()->delete($this->model);

        return new EmptyResponse;
    }
}
