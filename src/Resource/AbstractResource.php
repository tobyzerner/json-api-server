<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\ProvidesResourceLinks;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Id;
use Tobyz\JsonApiServer\SchemaContext;

abstract class AbstractResource extends AbstractCollection implements Resource
{
    public function name(): string
    {
        return $this->type();
    }

    public function resources(): array
    {
        return [$this->type()];
    }

    public function resource(object $model, Context $context): ?string
    {
        return $this->type();
    }

    public function fields(): array
    {
        return [];
    }

    public function meta(): array
    {
        return [];
    }

    public function links(): array
    {
        return [];
    }

    public function id(): Id
    {
        return Id::make();
    }

    public function getValue(object $model, Field $field, Context $context): mixed
    {
        return $model->{$field->property ?: $field->name} ?? null;
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $this->type();
        $id = $this->id();
        $context = $context->withResource($this);

        $schema = [
            'type' => 'object',
            'required' => ['type'],
            'properties' => [
                'type' => ['type' => 'string', 'const' => $type],
                'attributes' => ['type' => 'object'],
                'relationships' => ['type' => 'object'],
            ],
        ];

        $createSchema = $schema;

        $updateSchema = array_merge_recursive($schema, [
            'required' => ['id'],
            'properties' => ['id' => $id->getSchema($context)],
        ]);

        foreach ([$id, ...$this->fields()] as $field) {
            $location = $field::location();
            $valueSchema = (object) $field->getSchema($context);

            if ($field instanceof Relationship) {
                $relationshipSchema = "{$type}_relationship_{$field->name}";
                $schemas[$relationshipSchema] = $valueSchema;
                $valueSchema = ['$ref' => "#/components/schemas/$relationshipSchema"];
            }

            if ($location) {
                $fieldSchema = &$schema['properties'][$location];
                $fieldUpdateSchema = &$updateSchema['properties'][$location];
                $fieldCreateSchema = &$createSchema['properties'][$location];
            } else {
                $fieldSchema = &$schema;
                $fieldUpdateSchema = &$updateSchema;
                $fieldCreateSchema = &$createSchema;
            }

            $fieldSchema['properties'][$field->name] = $valueSchema;
            $fieldSchema['required'][] = $field->name;

            if ($field->writable) {
                $fieldUpdateSchema['properties'][$field->name] = $valueSchema;
            }

            if ($field->writableOnCreate) {
                $fieldCreateSchema['properties'][$field->name] = $valueSchema;
                if ($field->required) {
                    $fieldCreateSchema['required'][] = $field->name;
                }
            }
        }

        $links = [];

        foreach ($context->api->getResourceCollections($this->type()) as $collection) {
            foreach ($collection->endpoints() as $endpoint) {
                if ($endpoint instanceof ProvidesResourceLinks) {
                    foreach ($endpoint->resourceLinks($context) as $link) {
                        $links[$link->name] = $link->getSchema($context);
                    }
                }
            }
        }

        if ($links) {
            $schema['properties']['links'] = ['type' => 'object', 'properties' => $links];
        }

        $schemas[$type] = $schema;
        $schemas["{$type}_create"] = $createSchema;
        $schemas["{$type}_update"] = $updateSchema;

        $pathsSchema = parent::rootSchema($context);

        return array_replace_recursive(['components' => ['schemas' => $schemas]], $pathsSchema);
    }
}
