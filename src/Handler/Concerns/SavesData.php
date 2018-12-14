<?php

namespace Tobscure\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tobscure\JsonApiServer\Exception\BadRequestException;
use Tobscure\JsonApiServer\Exception\UnprocessableEntityException;
use Tobscure\JsonApiServer\ResourceType;
use Tobscure\JsonApiServer\Schema;

trait SavesData
{
    use FindsResources;

    private function save($model, Request $request, bool $creating = false): void
    {
        $data = $this->parseData($request->getParsedBody());

        $adapter = $this->resource->getAdapter();

        $this->assertFieldsExist($data);

        $this->assertFieldsWritable($data, $model, $request);

        if ($creating) {
            $this->fillDefaultValues($data, $request);
        }

        $this->loadRelatedResources($data, $request);

        $this->assertDataValid($data, $model, $request, $creating);

        $this->applyValues($data, $model, $request);

        $adapter->save($model);

        $this->saveFields($data, $model, $request);
    }

    private function parseData($body): array
    {
        if (! is_array($body) && ! is_object($body)) {
            throw new BadRequestException;
        }

        $body = (array) $body;

        if (! isset($body['data'])) {
            throw new BadRequestException;
        }

        if (isset($body['data']['attributes']) && ! is_array($body['data']['attributes'])) {
            throw new BadRequestException;
        }

        if (isset($body['data']['relationships']) && ! is_array($body['data']['relationships'])) {
            throw new BadRequestException;
        }

        return array_merge(
            ['attributes' => [], 'relationships' => []],
            $body['data']
        );
    }

    private function getModelForIdentifier(Request $request, $identifier)
    {
        if (! isset($identifier['type']) || ! isset($identifier['id'])) {
            throw new BadRequestException('type/id not specified');
        }

        $resource = $this->api->getResource($identifier['type']);

        return $this->findResource($request, $resource, $identifier['id']);
    }

    private function assertFieldsExist(array $data)
    {
        $schema = $this->resource->getSchema();

        foreach (['attributes', 'relationships'] as $location) {
            foreach ($data[$location] as $name => $value) {
                if (! isset($schema->fields[$name])
                    || $location !== $schema->fields[$name]->location
                ) {
                    throw new BadRequestException("Unknown field [$name]");
                }
            }
        }
    }

    private function assertFieldsWritable(array $data, $model, Request $request)
    {
        $schema = $this->resource->getSchema();

        foreach ($schema->fields as $name => $field) {
            $valueProvided = isset($data[$field->location][$name]);

            if ($valueProvided && ! ($field->isWritable)($model, $request)) {
                throw new BadRequestException("Field [$name] is not writable");
            }
        }
    }

    private function fillDefaultValues(array &$data, Request $request)
    {
        $schema = $this->resource->getSchema();

        foreach ($schema->fields as $name => $field) {
            $valueProvided = isset($data[$field->location][$name]);

            if (! $valueProvided && $field->default) {
                $data[$field->location][$name] = ($field->default)($request);
            }
        }
    }

    private function loadRelatedResources(array &$data, Request $request)
    {
        $schema = $this->resource->getSchema();

        foreach ($schema->fields as $name => $field) {
            if (! isset($data[$field->location][$name])) {
                continue;
            }

            $value = &$data[$field->location][$name];

            if ($field instanceof Schema\HasOne) {
                $value = $this->getModelForIdentifier($request, $value['data']);
            } elseif ($field instanceof Schema\HasMany) {
                $value = array_map(function ($identifier) use ($request) {
                    return $this->getModelForIdentifier($request, $identifier);
                }, $value['data']);
            }
        }
    }

    private function assertDataValid(array $data, $model, Request $request, bool $all): void
    {
        $schema = $this->resource->getSchema();

        $failures = [];

        foreach ($schema->fields as $name => $field) {
            if (! $all && ! isset($data[$field->location][$name])) {
                continue;
            }

            $fail = function ($message) use (&$failures, $field, $name) {
                $failures[$field->location][$name][] = $message;
            };

            foreach ($field->validators as $validator) {
                $validator($fail, $data[$field->location][$name], $model, $request);
            }
        }

        if (count($failures)) {
            throw new UnprocessableEntityException(print_r($failures, true));
        }
    }

    private function applyValues(array $data, $model, Request $request)
    {
        $schema = $this->resource->getSchema();
        $adapter = $this->resource->getAdapter();

        foreach ($schema->fields as $name => $field) {
            if (! isset($data[$field->location][$name])) {
                continue;
            }

            $value = $data[$field->location][$name];

            if ($field->setter || $field->saver) {
                if ($field->setter) {
                    ($field->setter)($model, $value, $request);
                }

                continue;
            }

            if ($field instanceof Schema\Attribute) {
                $adapter->applyAttribute($model, $field, $value);
            } elseif ($field instanceof Schema\HasOne) {
                $adapter->applyHasOne($model, $field, $value);
            }
        }
    }

    private function saveFields(array $data, $model, Request $request)
    {
        $schema = $this->resource->getSchema();
        $adapter = $this->resource->getAdapter();

        foreach ($schema->fields as $name => $field) {
            if (! isset($data[$field->location][$name])) {
                continue;
            }

            $value = $data[$field->location][$name];

            if ($field->saver) {
                ($field->saver)($model, $value, $request);
            } elseif ($field instanceof Schema\HasMany) {
                $adapter->saveHasMany($model, $field, $value);
            }
        }
    }
}
