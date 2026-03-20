<?php

namespace Tobyz\JsonApiServer;

use Closure;
use RuntimeException;
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

        $this->initializeResource($key, $type, $id, $context);
        $this->serializeFields($key, $context);
        $this->serializeMeta($key, $context);
        $this->mergeResourceMeta($key, $context);
        $this->serializeLinks($key, $context);

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
        $this->resolveValue(
            $value,
            fn($value) => $this->writeFieldValue($key, $field, $context, $value),
        );
    }

    private function resolveMetaValue(
        string $key,
        Field $field,
        Context $context,
        mixed $value,
    ): void {
        $this->resolveValue(
            $value,
            fn($value) => ($this->map[$key]['meta'][$field->name] = $field->serializeValue(
                $value,
                $context,
            )),
        );
    }

    private function resolveLinkValue(
        string $key,
        Field $field,
        Context $context,
        mixed $value,
    ): void {
        $this->resolveValue(
            $value,
            fn($value) => ($this->map[$key]['links'][$field->name] = $field->serializeValue(
                $value,
                $context,
            )),
        );
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

    private function initializeResource(
        string $key,
        string $type,
        string $id,
        Context $context,
    ): void {
        if (isset($this->map[$key])) {
            return;
        }

        $this->map[$key] = [
            'type' => $type,
            'id' => $id,
        ];

        foreach ($context->resourceLinkDefinitions() as [$field, $collection]) {
            $linkContext = $context->withCollection($collection)->withField($field);

            if (!$field->isVisible($linkContext)) {
                continue;
            }

            $this->resolveLinkValue($key, $field, $linkContext, $field->getValue($linkContext));
        }
    }

    private function serializeFields(string $key, Context $context): void
    {
        foreach ($context->sparseFields($context->resource) as $field) {
            if ($this->fieldProcessed($key, $field)) {
                continue;
            }

            $fieldContext = $context
                ->withField($field)
                ->withInclude($context->include[$field->name] ?? null);

            if (!$field->isVisible($fieldContext)) {
                continue;
            }

            $this->resolveFieldValue($key, $field, $fieldContext, $field->getValue($fieldContext));
        }
    }

    private function serializeMeta(string $key, Context $context): void
    {
        foreach ($context->meta($context->resource) as $field) {
            if (array_key_exists($field->name, $this->map[$key]['meta'] ?? [])) {
                continue;
            }

            $metaContext = $context->withField($field);

            if (!$field->isVisible($metaContext)) {
                continue;
            }

            $this->resolveMetaValue($key, $field, $metaContext, $field->getValue($metaContext));
        }
    }

    private function mergeResourceMeta(string $key, Context $context): void
    {
        foreach ($context->resourceMeta[$context->model] ?? [] as $name => $value) {
            $this->map[$key]['meta'][$name] = $value;
        }
    }

    private function serializeLinks(string $key, Context $context): void
    {
        foreach ($context->links($context->resource) as $field) {
            if (array_key_exists($field->name, $this->map[$key]['links'] ?? [])) {
                continue;
            }

            $linkContext = $context->withField($field);

            if (!$field->isVisible($linkContext)) {
                continue;
            }

            $this->resolveLinkValue($key, $field, $linkContext, $field->getValue($linkContext));
        }
    }

    private function fieldProcessed(string $key, Field $field): bool
    {
        if (in_array($field, $this->processedFields[$key] ?? [])) {
            return true;
        }

        $this->processedFields[$key][] = $field;

        return false;
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

    private function resolveValue(mixed $value, callable $resolve): void
    {
        if ($value instanceof Closure) {
            $this->deferred[] = fn() => $resolve($value());
            return;
        }

        $resolve($value);
    }

    private function writeFieldValue(
        string $key,
        Field $field,
        Context $context,
        mixed $value,
    ): void {
        if (
            ($value = $field->serializeValue($value, $context)) ||
            !$field instanceof Relationship
        ) {
            set_value($this->map[$key], $field, $value);
        }
    }
}
