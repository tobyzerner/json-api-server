<?php

namespace Tobyz\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema;

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

        $this->saveModel($model, $request);

        $this->saveFields($data, $model, $request);

        $this->runSavedCallbacks($data, $model, $request);
    }

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

    private function getModelForIdentifier(Request $request, $identifier)
    {
        if (! isset($identifier['type'])) {
            throw new BadRequestException('type not specified');
        }

        if (! isset($identifier['id'])) {
            throw new BadRequestException('id not specified');
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

            if ($valueProvided && ! ($field->isWritable)($request, $model)) {
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

            if ($field instanceof Schema\Relationship) {
                $value = $value['data'];

                if ($value) {
                    if ($field instanceof Schema\HasOne) {
                        $value = $this->getModelForIdentifier($request, $value);
                    } elseif ($field instanceof Schema\HasMany) {
                        $value = array_map(function ($identifier) use ($request) {
                            return $this->getModelForIdentifier($request, $identifier);
                        }, $value);
                    }
                }
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

            $fail = function ($message) use (&$failures, $field) {
                $failures[] = compact('field', 'message');
            };

            foreach ($field->validators as $validator) {
                $validator($fail, $data[$field->location][$name] ?? null, $model, $request, $field);
            }
        }

        if (count($failures)) {
            throw new UnprocessableEntityException($failures);
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
                    ($field->setter)($request, $model, $value);
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

    private function saveModel($model, Request $request)
    {
        $adapter = $this->resource->getAdapter();
        $schema = $this->resource->getSchema();

        if ($schema->saver) {
            ($schema->saver)($request, $model);
        } else {
            $adapter->save($model);
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
                ($field->saver)($request, $model, $value);
            } elseif ($field instanceof Schema\HasMany) {
                $adapter->saveHasMany($model, $field, $value);
            }
        }
    }

    private function runSavedCallbacks(array $data, $model, Request $request)
    {
        $schema = $this->resource->getSchema();

        foreach ($schema->fields as $name => $field) {
            if (! isset($data[$field->location][$name])) {
                continue;
            }

            foreach ($field->savedCallbacks as $callback) {
                $callback($request, $model, $data[$field->location][$name]);
            }
        }
    }
}
