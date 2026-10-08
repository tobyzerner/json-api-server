<?php

namespace Tobyz\JsonApiServer\Extension\Hook;

use InvalidArgumentException;
use ReflectionClass;

/**
 * A placeholder in an endpoint's parameters for the parameters of a hook that
 * the endpoint runs. It is replaced by the parameters of that hook from each
 * extension that applies.
 */
final class HookParameters
{
    private function __construct(
        public readonly string $hook,
    ) {}

    /**
     * @param class-string<ParameterizedHook> $hook
     */
    public static function for(string $hook): self
    {
        if (
            !is_a($hook, ParameterizedHook::class, true)
            || (new ReflectionClass($hook))->isAbstract()
        ) {
            throw new InvalidArgumentException("$hook hooks cannot define parameters.");
        }

        return new self($hook);
    }
}
