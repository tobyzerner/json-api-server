<?php

namespace Tobyz\JsonApiServer;

use Closure;
use RuntimeException;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

class Serializer
{
    private Context $context;
    private array $map = [];
    private array $primary = [];
    private array $deferred = [];

    public function __construct(Context $context)
    {
        $this->context = $context->withSerializer($this);
    }

    /**
     * Add a primary resource to the document.
     */
    public function addPrimary(Resource $resource, mixed $model, array $include): void
    {
        $data = $this->addToMap($resource, $model, $include);

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

    private function addToMap(Resource $resource, mixed $model, array $include): array
    {
        $context = $this->context->withResource($resource)->withModel($model);

        $key = $this->key($type = $resource->type(), $id = $resource->getId($model, $context));

        $url = "{$context->api->basePath}/$type/$id";

        if (!isset($this->map[$key])) {
            $this->map[$key] = [
                'type' => $type,
                'id' => $id,
                'links' => [
                    'self' => $url,
                ],
            ];
        }

        foreach ($this->context->sparseFields($resource) as $field) {
            if (has_value($this->map[$key], $field)) {
                continue;
            }

            $context = $context->withField($field)->withInclude($include[$field->name] ?? null);

            if (!$field->isVisible($context)) {
                continue;
            }

            $value = $field->getValue($context);

            $this->whenResolved($value, function (mixed $value) use ($key, $field, $context) {
                if (
                    ($value = $field->serializeValue($value, $context)) ||
                    !$field instanceof Relationship
                ) {
                    set_value($this->map[$key], $field, $value);
                }
            });
        }

        // TODO: cache
        foreach ($resource->meta() as $field) {
            if (
                array_key_exists($field->name, $this->map[$key]['meta'] ?? []) ||
                !$field->isVisible($context)
            ) {
                continue;
            }

            $value = $field->getValue($context);

            $this->whenResolved($value, function (mixed $value) use ($key, $field, $context) {
                $this->map[$key]['meta'][$field->name] = $field->serializeValue($value, $context);
            });
        }

        return $this->map[$key];
    }

    private function key(string $type, string $id): string
    {
        return "$type:$id";
    }

    private function whenResolved($value, $callback): void
    {
        if ($value instanceof Closure) {
            $this->deferred[] = fn() => $this->whenResolved($value(), $callback);
            return;
        }

        $callback($value);
    }

    /**
     * Add an included resource to the document.
     *
     * @return array The resource identifier which can be used for linkage.
     */
    public function addIncluded(Relationship $field, $model, ?array $include): array
    {
        $relatedResource = $this->resourceForModel($field, $model);

        if ($include === null) {
            return [
                'type' => $relatedResource->type(),
                'id' => $relatedResource->getId($model, $this->context),
            ];
        }

        $data = $this->addToMap($relatedResource, $model, $include);

        return [
            'type' => $data['type'],
            'id' => $data['id'],
        ];
    }

    private function resourceForModel(Relationship $field, $model): Resource
    {
        foreach ($field->collections as $name) {
            $collection = $this->context->api->getCollection($name);

            if ($type = $collection->resource($model, $this->context)) {
                return $this->context->api->getResource($type);
            }
        }

        throw new RuntimeException(
            'No resource type defined to represent model ' . get_class($model),
        );
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
