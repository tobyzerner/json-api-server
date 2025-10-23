<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Closure;
use Tobyz\JsonApiServer\Context;

trait HasSavedCallbacks
{
    /**
     * @var Closure[]
     */
    protected array $savedCallbacks = [];

    /**
     * Register a callback to run after the resource has been persisted but before the response is built.
     */
    public function saved(Closure $callback): static
    {
        $this->savedCallbacks[] = $callback;

        return $this;
    }

    protected function runSavedCallbacks(Context $context): Context
    {
        foreach ($this->savedCallbacks as $callback) {
            $result = isset($context->model)
                ? $callback($context->model, $context)
                : $callback($context);

            if ($result instanceof Context) {
                $context = $result;
            }
        }

        return $context;
    }
}
