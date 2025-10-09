<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\ProvidesRelationshipLinks;
use Tobyz\JsonApiServer\Exception\Data\InvalidIdException;
use Tobyz\JsonApiServer\Exception\Data\InvalidTypeException;
use Tobyz\JsonApiServer\Exception\Data\UnsupportedTypeException;
use Tobyz\JsonApiServer\Exception\Relationship\InvalidRelationshipException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Tobyz\JsonApiServer\SchemaContext;

abstract class Relationship extends Field
{
    use HasMeta;
    use FindsResources;

    public array $collections;
    public bool $includable = false;
    public bool|Closure $linkage = false;
    public array $linkageMeta = [];

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function location(): ?string
    {
        return 'relationships';
    }

    /**
     * Set the collection(s) that this relationship is to.
     */
    public function collection(string|array $type): static
    {
        $this->collections = (array) $type;

        return $this;
    }

    /**
     * Set the collection(s) that this relationship is to.
     */
    public function type(string|array $type): static
    {
        return $this->collection($type);
    }

    /**
     * Allow this relationship to be included.
     */
    public function includable(): static
    {
        $this->includable = true;

        return $this;
    }

    /**
     * Don't allow this relationship to be included.
     */
    public function notIncludable(): static
    {
        $this->includable = false;

        return $this;
    }

    /**
     * Include linkage for this relationship.
     */
    public function withLinkage(bool|Closure $condition = true): static
    {
        $this->linkage = $condition;

        return $this;
    }

    /**
     * Don't include linkage for this relationship.
     */
    public function withoutLinkage(): static
    {
        return $this->withLinkage(false);
    }

    /**
     * Define meta fields for resource identifier objects in linkage.
     */
    public function linkageMeta(array $fields): static
    {
        $this->linkageMeta = array_merge($this->linkageMeta, $fields);

        return $this;
    }

    public function getValue(Context $context): mixed
    {
        if ($context->include === null && !$this->hasLinkage($context)) {
            return null;
        }

        return parent::getValue($context);
    }

    public function serializeValue($value, Context $context): array
    {
        $relationship =
            $context->include !== null || $this->hasLinkage($context)
                ? $this->serializeData($value, $context)
                : [];

        if ($meta = $this->serializeMeta($context)) {
            $relationship['meta'] = $meta;
        }

        static $linkFieldsCache = [];
        $cacheKey = $context->resource->type() . '-' . $this->name;

        if (!isset($linkFieldsCache[$cacheKey])) {
            foreach ($context->endpoints($context->collection) as $endpoint) {
                if ($endpoint instanceof ProvidesRelationshipLinks) {
                    foreach ($endpoint->relationshipLinks($this, $context) as $field) {
                        $linkFieldsCache[$cacheKey][$field->name] ??= $field;
                    }
                }
            }
        }

        if ($links = $this->serializeLinks($linkFieldsCache[$cacheKey] ?? [], $context)) {
            $relationship['links'] = $links;
        }

        return $relationship;
    }

    public function serializeLinks(array $linkFields, Context $context): array
    {
        $links = [];

        foreach ($linkFields as $field) {
            if (!$field->isVisible($context)) {
                continue;
            }

            $value = $field->getValue($context);

            $links[$field->name] = $field->serializeValue($value, $context);
        }

        return $links;
    }

    abstract protected function serializeData($value, Context $context): array;

    protected function serializeIdentifier($model, Context $context): array
    {
        $context = $context->forModel($this->collections, $model);

        $identifier = $context->serializer->addIncluded($context);

        if ($this->linkageMeta && ($meta = $this->serializeLinkageMeta($context))) {
            $identifier['meta'] = $meta;
        }

        return $identifier;
    }

    protected function serializeLinkageMeta(Context $context): array
    {
        $meta = [];

        foreach ($this->linkageMeta as $field) {
            if (!$field->isVisible($context)) {
                continue;
            }

            $value = $field->getValue($context);

            $meta[$field->name] = $field->serializeValue($value, $context);
        }

        return $meta;
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if (!is_array($value) || !array_key_exists('data', $value)) {
            throw new InvalidRelationshipException();
        }

        try {
            $resolved = $this->deserializeData($value['data'], $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['pointer' => '/data']);
        }

        if ($this->deserializer) {
            return ($this->deserializer)($resolved, $context);
        }

        return $resolved;
    }

    abstract protected function deserializeData(mixed $data, Context $context): mixed;

    public function hasLinkage(Context $context): mixed
    {
        if ($this->linkage instanceof Closure) {
            return ($this->linkage)($context);
        }

        return $this->linkage;
    }

    public function getSchema(SchemaContext $context): array
    {
        $schema = parent::getSchema($context);

        unset($schema['nullable']);

        if ($this->required) {
            $schema['required'] = ['data'];
        }

        $meta = [];

        foreach ($this->meta as $m) {
            $meta[$m->name] = $m->getSchema($context);
        }

        $links = [];

        foreach ($context->api->getResourceCollections($context->resource->type()) as $collection) {
            foreach ($collection->endpoints() as $endpoint) {
                if ($endpoint instanceof ProvidesRelationshipLinks) {
                    foreach ($endpoint->relationshipLinks($this, $context) as $link) {
                        $links[$link->name] = $link->getSchema($context);
                    }
                }
            }
        }

        return $schema + [
            'type' => 'object',
            'properties' => [
                'data' => $this->getDataSchema($context),
                ...$links ? ['links' => ['type' => 'object', 'properties' => $links]] : [],
                ...$meta ? ['meta' => ['type' => 'object', 'properties' => $meta]] : [],
            ],
        ];
    }

    protected function resourceForIdentifier(array $identifier, Context $context): mixed
    {
        if (!is_string($identifier['type'] ?? null)) {
            throw (new InvalidTypeException())->source([
                'pointer' => array_key_exists('type', $identifier) ? '/type' : '',
            ]);
        }

        if (!is_string($identifier['id'] ?? null)) {
            throw (new InvalidIdException())->source([
                'pointer' => array_key_exists('id', $identifier) ? '/id' : '',
            ]);
        }

        $resources = $this->getRelatedResources($context);

        if (in_array($identifier['type'], $resources)) {
            return $this->findResource(
                $context->withCollection($context->resource($identifier['type'])),
                $identifier['id'],
            );
        }

        throw (new UnsupportedTypeException($identifier['type']))->source(['pointer' => '/type']);
    }

    protected function getRelatedResources(SchemaContext $context): array
    {
        return array_merge(
            ...array_map(
                fn($collection) => $context->api->getCollection($collection)->resources(),
                $this->collections,
            ),
        );
    }

    abstract protected function getDataSchema(SchemaContext $context): array;

    protected function getLinkageSchema(SchemaContext $context): array
    {
        $resources = $this->getRelatedResources($context);

        $meta = [];

        foreach ($this->linkageMeta as $field) {
            $meta[$field->name] = $field->getSchema($context);
        }

        return [
            'allOf' => [
                ['$ref' => '#/components/schemas/jsonApiResourceIdentifier'],
                [
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            ...count($resources) === 1
                                ? ['const' => $resources[0]]
                                : ['enum' => $resources],
                        ],
                        ...$meta ? ['meta' => ['type' => 'object', 'properties' => $meta]] : [],
                    ],
                ],
            ],
        ];
    }
}
