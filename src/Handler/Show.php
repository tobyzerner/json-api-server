<?php

namespace Tobyz\JsonApiServer\Handler;

use JsonApiPhp\JsonApi;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Api;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApiResponse;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Serializer;

class Show implements RequestHandlerInterface
{
    use Concerns\IncludesData;

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

        if (! ($schema->isVisible)($request)) {
            throw new ForbiddenException('You cannot view this resource');
        }

        $include = $this->getInclude($request);

        $this->loadRelationships([$this->model], $include, $request);

        $serializer = new Serializer($this->api, $request);

        $serializer->add($this->resource, $this->model, $include);

        return new JsonApiResponse(
            new JsonApi\CompoundDocument(
                $serializer->primary()[0],
                new JsonApi\Included(...$serializer->included())
            )
        );
    }
}
