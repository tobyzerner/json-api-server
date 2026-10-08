<?php

namespace Tobyz\JsonApiServer\Extension\Hook;

use Closure;

/**
 * A point at which an extension changes how requests are handled.
 */
abstract class Hook
{
    final public function __construct(
        protected readonly Closure $callback,
    ) {}

    public static function make(Closure $callback): static
    {
        return new static($callback);
    }
}
