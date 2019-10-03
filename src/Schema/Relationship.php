<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use function Tobyz\JsonApiServer\negate;

abstract class Relationship extends Field
{
    private $type;
    private $allowedTypes;
    private $linkage = false;
    private $links = true;
    private $loadable = true;
    private $includable = false;
    private $scopes = [];

    public function type($type)
    {
        $this->type = $type;

        return $this;
    }

    public function polymorphic(array $types = null)
    {
        $this->type = null;
        $this->allowedTypes = $types;

        return $this;
    }

    public function linkage(Closure $condition = null)
    {
        $this->linkage = $condition ?: true;

        return $this;
    }

    public function noLinkage(Closure $condition = null)
    {
        $this->linkage = $condition ? negate($condition) : false;

        return $this;
    }

    public function loadable(Closure $callback = null)
    {
        $this->loadable = $callback ?: true;

        return $this;
    }

    public function notLoadable()
    {
        $this->loadable = false;

        return $this;
    }

    public function includable()
    {
        $this->includable = true;

        return $this;
    }

    public function notIncludable()
    {
        $this->includable = false;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getAllowedTypes(): ?array
    {
        return $this->allowedTypes;
    }

    public function links()
    {
        $this->links = true;

        return $this;
    }

    public function noLinks()
    {
        $this->links = false;

        return $this;
    }

    public function getLinkage()
    {
        return $this->linkage;
    }

    public function hasLinks(): bool
    {
        return $this->links;
    }

    /**
     * @return bool|Closure
     */
    public function getLoadable()
    {
        return $this->loadable;
    }

    public function isIncludable(): bool
    {
        return $this->includable;
    }

    public function getLocation(): string
    {
        return 'relationships';
    }

    public function scope(Closure $callback)
    {
        $this->scopes[] = $callback;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }
}
