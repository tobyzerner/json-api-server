<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\run_callbacks;
use function Tobyz\JsonApiServer\set_value;

class Create
{
    use Concerns\SavesData;

    private $api;
    private $resource;

    public function __construct(JsonApi $api, ResourceType $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
    }

    /**
     * @throws ForbiddenException if the resource is not creatable.
     */
    public function handle(Context $context): ResponseInterface
    {
        $schema = $this->resource->getSchema();

        if (! evaluate($schema->isCreatable(), [$context])) {
            throw new ForbiddenException;
        }

        $model = $this->newModel($context);
        $data = $this->parseData($context->getRequest()->getParsedBody());

        $this->validateFields($data, $model, $context);
        $this->fillDefaultValues($data, $context);
        $this->loadRelatedResources($data, $context);
        $this->assertDataValid($data, $model, $context, true);
        $this->setValues($data, $model, $context);

        run_callbacks($schema->getListeners('creating'), [$model, $context]);

        $this->save($data, $model, $context);

        run_callbacks($schema->getListeners('created'), [$model, $context]);

        return (new Show($this->api, $this->resource, $model))
            ->handle($context)
            ->withStatus(201);
    }

    private function newModel(Context $context)
    {
        $resource = $this->resource;
        $newModel = $resource->getSchema()->getNewModelCallback();

        return $newModel
            ? $newModel($context)
            : $resource->getAdapter()->newModel();
    }

    private function fillDefaultValues(array &$data, Context $context)
    {
        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! has_value($data, $field) && ($defaultCallback = $field->getDefaultCallback())) {
                set_value($data, $field, $defaultCallback($context));
            }
        }
    }
}
