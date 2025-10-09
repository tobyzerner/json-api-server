<?php

namespace Tobyz\JsonApiServer;

use Closure;
use RuntimeException;
use Tobyz\JsonApiServer\Endpoint\ProvidesResourceLinks;
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

            static $linkFieldsCache = [];

            if (!isset($linkFieldsCache[$type])) {
                foreach ($context->api->getResourceCollections($type) as $collection) {
                    $collectionContext = $context->withCollection($collection);

                    foreach ($context->endpoints($collection) as $endpoint) {
                        if ($endpoint instanceof ProvidesResourceLinks) {
                            foreach ($endpoint->resourceLinks($collectionContext) as $field) {
                                $linkFieldsCache[$type][$field->name] ??= $field;
                            }
                        }
                    }
                }
            }

            foreach ($linkFieldsCache[$type] ?? [] as $field) {
                if (!$field->isVisible($linkContext = $context->withField($field))) {
                    continue;
                }

                $value = $field->getValue($linkContext);

                $this->resolveLinkValue($key, $field, $linkContext, $value);
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

        foreach ($context->meta($resource) as $field) {
            if (
                array_key_exists($field->name, $this->map[$key]['meta'] ?? []) ||
                !$field->isVisible($metaContext = $context->withField($field))
            ) {
                continue;
            }

            $value = $field->getValue($metaContext);

            $this->resolveMetaValue($key, $field, $metaContext, $value);
        }

        foreach ($context->resourceMeta[$model] ?? [] as $k => $v) {
            $this->map[$key]['meta'][$k] = $v;
        }

        foreach ($context->links($resource) as $link) {
            if (
                array_key_exists($link->name, $this->map[$key]['links'] ?? []) ||
                !$link->isVisible($linkContext = $context->withField($link))
            ) {
                continue;
            }

            $value = $link->getValue($linkContext);

            $this->resolveLinkValue($key, $link, $linkContext, $value);
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

    private function resolveLinkValue(
        string $key,
        Field $field,
        Context $context,
        mixed $value,
    ): void {
        if ($value instanceof Closure) {
            $this->deferred[] = fn() => $this->resolveLinkValue($key, $field, $context, $value());
        } else {
            $this->map[$key]['links'][$field->name] = $field->serializeValue($value, $context);
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
