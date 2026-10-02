<?php

namespace Tobyz\JsonApiServer\Laravel\Field\Concerns;

use ArrayObject;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Tobyz\JsonApiServer\Context;
use WeakMap;

trait LoadsRelations
{
    /** @var Closure[] */
    public array $loaders = [];

    /** @var WeakMap<\Tobyz\JsonApiServer\Serializer, ArrayObject>|null */
    private ?WeakMap $loadBuffer = null;

    /**
     * Declare Eloquent relations that this field's value depends on.
     *
     * The relations are loaded in a single batch for all models where the
     * field is requested and visible, before the value is retrieved. Pass a
     * closure to load data some other way; it receives a collection of the
     * models and the context.
     */
    public function load(string|array|Closure $relations): static
    {
        if (!$relations instanceof Closure) {
            $relations = fn($models, Context $context) => $models->loadMissing(
                array_map(
                    fn($value) => $value instanceof Closure
                        ? fn($query) => $value($query, $context)
                        : $value,
                    (array) $relations,
                ),
            );
        }

        $this->loaders[] = $relations;

        return $this;
    }

    public function getValue(Context $context): mixed
    {
        if (!$this->loaders || !isset($context->model, $context->serializer)) {
            return parent::getValue($context);
        }

        // Buffer models per serializer so that a serialization which ends
        // early can't leak its models into the next one's batch.
        $this->loadBuffer ??= new WeakMap();
        $models = $this->loadBuffer[$context->serializer] ??= new ArrayObject();
        $models[] = $context->model;

        return function () use ($models, $context) {
            if (count($models)) {
                $collection = $this->newCollection($models->exchangeArray([]));

                foreach ($this->loaders as $loader) {
                    $loader($collection, $context);
                }
            }

            return parent::getValue($context);
        };
    }

    protected function newCollection(array $models): object
    {
        return new Collection($models);
    }
}
