<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
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
        $resourceSchema = $this->resourceSchema($type);

        $schemas = [
            $type => $resourceSchema,
            "{$type}_create" => $resourceSchema,
            "{$type}_update" => array_merge_recursive($resourceSchema, [
                'required' => ['id'],
                'properties' => ['id' => (object) $id->getSchema($context)],
            ]),
        ];
        $relationshipSchemas = [];

        foreach ([$id, ...$this->fields()] as $field) {
            $fieldSchema = $this->describeFieldSchema($type, $field, $context);
            $relationshipSchema = $fieldSchema['relationship'];

            if ($relationshipSchema) {
                $relationshipSchemas[$relationshipSchema['name']] = $relationshipSchema['schema'];
            }

            $schemas[$type] = $this->withFieldSchema(
                $schemas[$type],
                $field,
                $fieldSchema['value'],
                true,
            );

            if ($field->writable) {
                $schemas["{$type}_update"] = $this->withFieldSchema(
                    $schemas["{$type}_update"],
                    $field,
                    $fieldSchema['value'],
                );
            }

            if ($field->writableOnCreate) {
                $schemas["{$type}_create"] = $this->withFieldSchema(
                    $schemas["{$type}_create"],
                    $field,
                    $fieldSchema['value'],
                    $field->required,
                );
            }
        }

        if ($links = $this->resourceLinksSchema($context)) {
            $schemas[$type]['properties']['links'] = ['type' => 'object', 'properties' => $links];
        }

        $pathsSchema = parent::rootSchema($context);

        return array_replace_recursive(
            ['components' => ['schemas' => $relationshipSchemas + $schemas]],
            $pathsSchema,
        );
    }

    private function resourceSchema(string $type): array
    {
        return [
            'type' => 'object',
            'required' => ['type'],
            'properties' => [
                'type' => ['type' => 'string', 'const' => $type],
                'attributes' => ['type' => 'object'],
                'relationships' => ['type' => 'object'],
            ],
        ];
    }

    private function describeFieldSchema(
        string $type,
        Field $field,
        SchemaContext $context,
    ): array {
        $valueSchema = (object) $field->getSchema($context);

        if (!$field instanceof Relationship) {
            return ['value' => $valueSchema, 'relationship' => null];
        }

        $name = "{$type}_relationship_{$field->name}";

        return [
            'value' => ['$ref' => "#/components/schemas/$name"],
            'relationship' => ['name' => $name, 'schema' => $valueSchema],
        ];
    }

    private function withFieldSchema(
        array $schema,
        Field $field,
        mixed $valueSchema,
        bool $required = false,
    ): array {
        $location = $field::location();

        if ($location) {
            $schema['properties'][$location]['properties'][$field->name] = $valueSchema;

            if ($required) {
                $schema['properties'][$location]['required'][] = $field->name;
            }

            return $schema;
        }

        $schema['properties'][$field->name] = $valueSchema;

        if ($required) {
            $schema['required'][] = $field->name;
        }

        return $schema;
    }

    private function resourceLinksSchema(SchemaContext $context): array
    {
        $links = [];

        foreach ($context->resourceLinkDefinitions() as [$link]) {
            $links[$link->name] = $link->getSchema($context);
        }

        return $links;
    }
}
