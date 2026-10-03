<?php

use Illuminate\Container\Container;

// EloquentResource reads the app timezone through Laravel's config() helper,
// which is only defined by the full framework.
if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        return Container::getInstance()['config'][$key] ?? $default;
    }
}
