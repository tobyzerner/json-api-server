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
        $schema = $this->resource->getSchema();

        if (! ($schema->isCreatable)($request)) {
            throw new ForbiddenException('You cannot create this resource');
        }

        $model = $this->resource->getAdapter()->create();

        $data = $this->parseData($request->getParsedBody());

        $adapter = $this->resource->getAdapter();

        $this->assertFieldsExist($data);

        $this->assertFieldsWritable($data, $model, $request);

        $this->fillDefaultValues($data, $request);

        $this->loadRelatedResources($data, $request);

        $this->assertDataValid($data, $model, $request, true);

        $this->applyValues($data, $model, $request);

        foreach ($schema->creatingCallbacks as $callback) {
            $callback($request, $model);
        }

        $adapter->save($model);

        $this->saveFields($data, $model, $request);

        $this->runSavedCallbacks($data, $model, $request);

        foreach ($schema->createdCallbacks as $callback) {
            $callback($request, $model);
        }

        return (new Show($this->api, $this->resource, $model))
            ->handle($request)
            ->withStatus(201);
    }
}
