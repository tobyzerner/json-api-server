<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\get_value;
use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\run_callbacks;
use function Tobyz\JsonApiServer\set_value;

/**
 * @property JsonApi $api
 * @property ResourceType $resource
 */
trait SavesData
{
    use FindsResources;

    /**
     * Parse and validate a JSON:API document's `data` member.
     *
     * @throws BadRequestException if the `data` member is invalid.
     */
    private function parseData($body, $model = null): array
    {
        $body = (array) $body;

        if (! isset($body['data']) || ! is_array($body['data'])) {
            throw new BadRequestException('data must be an object');
        }

        if (! isset($body['data']['type']) || $body['data']['type'] !== $this->resource->getType()) {
            throw new BadRequestException('data.type does not match the resource type');
        }

        if ($model) {
            $id = $this->resource->getAdapter()->getId($model);

            if (! isset($body['data']['id']) || $body['data']['id'] !== $id) {
                throw new BadRequestException('data.id does not match the resource ID');
            }
        }

        if (isset($body['data']['attributes']) && ! is_array($body['data']['attributes'])) {
            throw new BadRequestException('data.attributes must be an object');
        }

        if (isset($body['data']['relationships']) && ! is_array($body['data']['relationships'])) {
            throw new BadRequestException('data.relationships must be an object');
        }

        return array_merge(
            ['attributes' => [], 'relationships' => []],
            $body['data']
        );
    }

    /**
     * Get the model corresponding to the given identifier.
     *
     * @throws BadRequestException if the identifier is invalid.
     */
    private function getModelForIdentifier(ServerRequestInterface $request, array $identifier, array $validTypes = null)
    {
        if (! isset($identifier['type'])) {
            throw new BadRequestException('type not specified');
        }

        if (! isset($identifier['id'])) {
            throw new BadRequestException('id not specified');
        }

        if ($validTypes !== null && count($validTypes) && ! in_array($identifier['type'], $validTypes)) {
            throw new BadRequestException("type [{$identifier['type']}] not allowed");
        }

        $resource = $this->api->getResource($identifier['type']);

        return $this->findResource($request, $resource, $identifier['id']);
    }

    /**
     * Assert that the fields contained within a data object are valid.
     */
    private function validateFields(array $data, $model, ServerRequestInterface $request)
    {
        $this->assertFieldsExist($data);
        $this->assertFieldsWritable($data, $model, $request);
    }

    /**
     * Assert that the fields contained within a data object exist in the schema.
     *
     * @throws BadRequestException if a field is unknown.
     */
    private function assertFieldsExist(array $data)
    {
        $fields = $this->resource->getSchema()->getFields();

        foreach (['attributes', 'relationships'] as $location) {
            foreach ($data[$location] as $name => $value) {
                if (! isset($fields[$name]) || $location !== $fields[$name]->getLocation()) {
                    throw new BadRequestException("Unknown field [$name]");
                }
            }
        }
    }

    /**
     * Assert that the fields contained within a data object are writable.
     *
     * @throws BadRequestException if a field is not writable.
     */
    private function assertFieldsWritable(array $data, $model, ServerRequestInterface $request)
    {

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (has_value($data, $field) && ! evaluate($field->getWritable(), [$model, $request])) {
                throw new BadRequestException("Field [{$field->getName()}] is not writable");
            }
        }
    }

    /**
     * Replace relationship linkage within a data object with models.
     */
    private function loadRelatedResources(array &$data, ServerRequestInterface $request)
    {
        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! $field instanceof Relationship || ! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if (isset($value['data'])) {
                $allowedTypes = (array) $field->getType();

                if ($field instanceof HasOne) {
                    set_value($data, $field, $this->getModelForIdentifier($request, $value['data'], $allowedTypes));
                } elseif ($field instanceof HasMany) {
                    set_value($data, $field, array_map(function ($identifier) use ($request, $allowedTypes) {
                        return $this->getModelForIdentifier($request, $identifier, $allowedTypes);
                    }, $value['data']));
                }
            } else {
                set_value($data, $field, null);
            }
        }
    }

    /**
     * Assert that the field values within a data object pass validation.
     *
     * @throws UnprocessableEntityException if any fields do not pass validation.
     */
    private function assertDataValid(array $data, $model, ServerRequestInterface $request, bool $validateAll): void
    {
        $failures = [];

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! $validateAll && ! has_value($data, $field)) {
                continue;
            }

            $fail = function ($message = null) use (&$failures, $field) {
                $failures[] = compact('field', 'message');
            };

            run_callbacks(
                $field->getListeners('validate'),
                [$fail, get_value($data, $field), $model, $request]
            );
        }

        if (count($failures)) {
            throw new UnprocessableEntityException($failures);
        }
    }

    /**
     * Set field values from a data object to the model instance.
     */
    private function setValues(array $data, $model, ServerRequestInterface $request)
    {
        $adapter = $this->resource->getAdapter();

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if ($setCallback = $field->getSetCallback()) {
                $setCallback($model, $value, $request);
                continue;
            }

            if ($field->getSaveCallback()) {
                continue;
            }

            if ($field instanceof Attribute) {
                $adapter->setAttribute($model, $field, $value);
            } elseif ($field instanceof HasOne) {
                $adapter->setHasOne($model, $field, $value);
            }
        }
    }

    /**
     * Save the model and its fields.
     */
    private function save(array $data, $model, ServerRequestInterface $request)
    {
        $this->saveModel($model, $request);
        $this->saveFields($data, $model, $request);
    }

    /**
     * Save the model.
     */
    private function saveModel($model, ServerRequestInterface $request)
    {
        if ($saveCallback = $this->resource->getSchema()->getSaveCallback()) {
            $saveCallback($model, $request);
        } else {
            $this->resource->getAdapter()->save($model);
        }
    }

    /**
     * Save any fields that were not saved with the model.
     */
    private function saveFields(array $data, $model, ServerRequestInterface $request)
    {
        $adapter = $this->resource->getAdapter();

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if ($saveCallback = $field->getSaveCallback()) {
                $saveCallback($model, $value, $request);
            } elseif ($field instanceof HasMany) {
                $adapter->saveHasMany($model, $field, $value);
            }
        }

        $this->runSavedCallbacks($data, $model, $request);
    }

    /**
     * Run field saved listeners.
     */
    private function runSavedCallbacks(array $data, $model, ServerRequestInterface $request)
    {

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            run_callbacks(
                $field->getListeners('saved'),
                [$model, get_value($data, $field), $request]
            );
        }
    }
}
