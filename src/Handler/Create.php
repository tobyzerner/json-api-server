<?php

namespace Tobscure\JsonApiServer\Handler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobscure\JsonApiServer\Api;
use Tobscure\JsonApiServer\Exception\ForbiddenException;
use Tobscure\JsonApiServer\ResourceType;

class Create implements RequestHandlerInterface
{
    use Concerns\SavesData;

    private $api;
    private $resource;

    public function __construct(Api $api, ResourceType $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
    }

    public function handle(Request $request): Response
    {
        if (! ($this->resource->getSchema()->isCreatable)($request)) {
            throw new ForbiddenException('You cannot create this resource');
        }

        $model = $this->resource->getAdapter()->create();

        $this->save($model, $request, true);

        return (new Show($this->api, $this->resource, $model))
            ->handle($request)
            ->withStatus(201);
    }
}
