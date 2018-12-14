<?php

namespace Tobscure\JsonApiServer\Handler;

use JsonApiPhp\JsonApi;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobscure\JsonApiServer\Api;
use Tobscure\JsonApiServer\JsonApiResponse;
use Tobscure\JsonApiServer\ResourceType;
use Tobscure\JsonApiServer\Serializer;

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
        $include = $this->getInclude($request);

        $this->load($include);

        $serializer = new Serializer($this->api, $request);

        $serializer->add($this->resource, $this->model, $include);

        return new JsonApiResponse(
            new JsonApi\CompoundDocument(
                $serializer->primary()[0],
                new JsonApi\Included(...$serializer->included())
            )
        );
    }

    private function load(array $include)
    {
        $adapter = $this->resource->getAdapter();

        $trails = $this->buildRelationshipTrails($this->resource, $include);

        foreach ($trails as $relationships) {
            $adapter->load($this->model, $relationships);
        }
    }
}
