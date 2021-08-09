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
use Tobyz\JsonApiServer\ResourceType;

use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\run_callbacks;
use function Tobyz\JsonApiServer\set_value;

class Create
{
    use Concerns\SavesData;

    /**
     * @throws ForbiddenException if the resource is not creatable.
     */
    public function handle(Context $context, ResourceType $resourceType): ResponseInterface
    {
        $schema = $resourceType->getSchema();

        if (! evaluate($schema->isCreatable(), [$context])) {
            throw new ForbiddenException();
        }

        $model = $this->newModel($resourceType, $context);
        $data = $this->parseData($resourceType, $context->getRequest()->getParsedBody());

        $this->validateFields($resourceType, $data, $model, $context);
        $this->fillDefaultValues($resourceType, $data, $context);
        $this->loadRelatedResources($resourceType, $data, $context);
        $this->assertDataValid($resourceType, $data, $model, $context, true);
        $this->setValues($resourceType, $data, $model, $context);

        run_callbacks($schema->getListeners('creating'), [&$model, $context]);

        $this->save($resourceType, $data, $model, $context);

        run_callbacks($schema->getListeners('created'), [&$model, $context]);

        return (new Show())
            ->handle($context, $resourceType, $model)
            ->withStatus(201);
    }

    private function newModel(ResourceType $resourceType, Context $context)
    {
        $newModel = $resourceType->getSchema()->getNewModelCallback();

        return $newModel
            ? $newModel($context)
            : $resourceType->getAdapter()->model();
    }

    private function fillDefaultValues(ResourceType $resourceType, array &$data, Context $context)
    {
        foreach ($resourceType->getSchema()->getFields() as $field) {
            if (! has_value($data, $field) && ($defaultCallback = $field->getDefaultCallback())) {
                set_value($data, $field, $defaultCallback($context));
            }
        }
    }
}
