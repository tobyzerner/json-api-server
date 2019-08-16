<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use function Tobyz\JsonApiServer\negate;

abstract class Relationship extends Field
{
    private $type;
    private $linkage = false;
    private $links = true;
    private $loadable = true;
    private $includable = false;

    public function type($type)
    {
        $this->type = $type;

        return $this;
    }

    public function polymorphic()
    {
        $this->type = null;

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

    public function getType()
    {
        return $this->type;
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
}
