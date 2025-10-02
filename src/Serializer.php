<?php

namespace Tobyz\JsonApiServer;

use Closure;
use RuntimeException;
use Tobyz\JsonApiServer\Endpoint\ResourceEndpoint;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

class Serializer
{
    private array $map = [];
    private array $primary = [];
    private array $deferred = [];
    private array $processedFields = [];

    /**
     * Add a primary resource to the document.
     */
    public function addPrimary(Context $context): void
    {
        $data = $this->addToMap($context->withSerializer($this));

        $this->primary[] = $this->key($data['type'], $data['id']);
    }

    /**
     * Serialize the primary and included resources into a JSON:API resource objects.
     *
     * @return array{array[], array[]} A tuple with primary resources and included resources.
     */
    public function serialize(): array
    {
        $this->resolveDeferred();

        $keys = array_flip($this->primary);
        $primary = array_values(array_intersect_key($this->map, $keys));
        $included = array_values(array_diff_key($this->map, $keys));

        return [$primary, $included];
    }

    private function addToMap(Context $context): array
    {
        $resource = $context->resource;
        $model = $context->model;

        $key = $this->key($type = $resource->type(), $id = $context->id($resource, $model));

        if (!isset($this->map[$key])) {
            $this->map[$key] = [
                'type' => $type,
                'id' => $id,
            ];

            foreach ($context->api->getResourceCollections($resource->type()) as $collection) {
                $collectionContext = $context->withCollection($collection);

                foreach ($context->endpoints($collection) as $endpoint) {
                    if ($endpoint instanceof ResourceEndpoint) {
                        if ($links = $endpoint->resourceLinks($model, $collectionContext)) {
                            $this->map[$key]['links'] ??= [];
                            $this->map[$key]['links'] += $links;
                        }
                    }
                }
            }
        }

        foreach ($context->sparseFields($resource) as $field) {
            if (in_array($field, $this->processedFields[$key] ?? [])) {
                continue;
            }

            $this->processedFields[$key][] = $field;

            $fieldContext = $context
                ->withField($field)
                ->withInclude($context->include[$field->name] ?? null);

            if (!$field->isVisible($fieldContext)) {
                continue;
            }

            $value = $field->getValue($fieldContext);

            $this->resolveFieldValue($key, $field, $fieldContext, $value);
        }

        // TODO: cache
        foreach ($resource->meta() as $field) {
            $metaContext = $context->withField($field);

            if (
                array_key_exists($field->name, $this->map[$key]['meta'] ?? []) ||
                !$field->isVisible($metaContext)
            ) {
                continue;
            }

            $value = $field->getValue($metaContext);

            $this->resolveMetaValue($key, $field, $metaContext, $value);
        }

        foreach ($context->resourceMeta[$model] ?? [] as $k => $v) {
            $this->map[$key]['meta'][$k] = $v;
        }

        return $this->map[$key];
    }

    private function key(string $type, string $id): string
    {
        return "$type:$id";
    }

    private function resolveFieldValue(
        string $key,
        Field $field,
        Context $context,
        mixed $value,
    ): void {
        if ($value instanceof Closure) {
            $this->deferred[] = fn() => $this->resolveFieldValue($key, $field, $context, $value());
        } elseif (
            ($value = $field->serializeValue($value, $context)) ||
            !$field instanceof Relationship
        ) {
            set_value($this->map[$key], $field, $value);
        }
    }

    private function resolveMetaValue(
        string $key,
        Field $field,
        Context $context,
        mixed $value,
    ): void {
        if ($value instanceof Closure) {
            $this->deferred[] = fn() => $this->resolveMetaValue($key, $field, $context, $value());
        } else {
            $this->map[$key]['meta'][$field->name] = $field->serializeValue($value, $context);
        }
    }

    /**
     * Add an included resource to the document.
     *
     * @return array The resource identifier which can be used for linkage.
     */
    public function addIncluded(Context $context): array
    {
        $context = $context->withSerializer($this);

        if ($context->include === null) {
            return [
                'type' => $context->resource->type(),
                'id' => $context->id($context->resource, $context->model),
            ];
        }

        $data = $this->addToMap($context);

        return [
            'type' => $data['type'],
            'id' => $data['id'],
        ];
    }

    private function resolveDeferred(): void
    {
        $i = 0;
        while (count($this->deferred)) {
            foreach ($this->deferred as $k => $resolve) {
                $resolve();
                unset($this->deferred[$k]);
            }

            if ($i++ > 10) {
                throw new RuntimeException('Too many levels of deferred values');
            }
        }
    }
}
