<?php

namespace Tobyz\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\get_value;
use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\set_value;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use function Tobyz\JsonApiServer\run_callbacks;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;

trait SavesData
{
    use FindsResources;

    private function parseData($body): array
    {
        if (! is_array($body) && ! is_object($body)) {
            throw new BadRequestException;
        }

        $body = (array) $body;

        if (! isset($body['data'])) {
            throw new BadRequestException('Root data attribute missing');
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

    private function getModelForIdentifier(Request $request, $identifier, array $validTypes = null)
    {
        if (! isset($identifier['type'])) {
            throw new BadRequestException('type not specified');
        }

        if (! isset($identifier['id'])) {
            throw new BadRequestException('id not specified');
        }

        if ($validTypes !== null && ! in_array($identifier['type'], $validTypes)) {
            throw new BadRequestException("type [{$identifier['type']}] not allowed");
        }

        $resource = $this->api->getResource($identifier['type']);

        return $this->findResource($request, $resource, $identifier['id']);
    }

    private function validateFields(array $data, $model, Request $request)
    {
        $this->assertFieldsExist($data);
        $this->assertFieldsWritable($data, $model, $request);
    }

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

    private function assertFieldsWritable(array $data, $model, Request $request)
    {
        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (has_value($data, $field) && ! evaluate($field->getWritable(), [$model, $request])) {
                throw new BadRequestException("Field [{$field->getName()}] is not writable");
            }
        }
    }

    private function loadRelatedResources(array &$data, Request $request)
    {
        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! $field instanceof Relationship || ! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if (isset($value['data'])) {
                $allowedTypes = $field->getAllowedTypes();

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

    private function assertDataValid(array $data, $model, Request $request, bool $all): void
    {
        $failures = [];

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! $all && ! has_value($data, $field)) {
                continue;
            }

            $fail = function ($message = null) use (&$failures, $field) {
                $failures[] = compact('field', 'message');
            };

            run_callbacks(
                $field->getListeners('validate'),
                [$fail, get_value($data, $field), $model, $request, $field, $data]
            );
        }

        if (count($failures)) {
            throw new UnprocessableEntityException($failures);
        }
    }

    private function setValues(array $data, $model, Request $request)
    {
        $adapter = $this->resource->getAdapter();

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if ($setter = $field->getSetter()) {
                $setter($model, $value, $request);
                continue;
            }

            if ($field->getSaver()) {
                continue;
            }

            if ($field instanceof Attribute) {
                $adapter->setAttribute($model, $field, $value);
            } elseif ($field instanceof HasOne) {
                $adapter->setHasOne($model, $field, $value);
            }
        }
    }

    private function save(array $data, $model, Request $request)
    {
        $this->saveModel($model, $request);
        $this->saveFields($data, $model, $request);
    }

    private function saveModel($model, Request $request)
    {
        if ($saver = $this->resource->getSchema()->getSaver()) {
            $saver($model, $request);
        } else {
            $this->resource->getAdapter()->save($model);
        }
    }

    private function saveFields(array $data, $model, Request $request)
    {
        $adapter = $this->resource->getAdapter();

        foreach ($this->resource->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if ($saver = $field->getSaver()) {
                $saver($model, $value, $request);
            } elseif ($field instanceof HasMany) {
                $adapter->saveHasMany($model, $field, $value);
            }
        }

        $this->runSavedCallbacks($data, $model, $request);
    }

    private function runSavedCallbacks(array $data, $model, Request $request)
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
