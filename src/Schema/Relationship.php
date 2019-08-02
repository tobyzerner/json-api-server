<?php

namespace Tobscure\JsonApiServer\Schema;

use Closure;
use Tobscure\JsonApiServer\Handler\Show;

abstract class Relationship extends Field
{
    public $location = 'relationships';
    public $linkage;
    public $hasLinks = true;
    public $loadable = true;
    public $loader;
    public $included = false;
    public $resource;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->noLinkage();
    }

    public function resource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    public function linkageIf(Closure $condition)
    {
        $this->linkage = $condition;

        return $this;
    }

    public function linkage()
    {
        return $this->linkageIf(function () {
            return true;
        });
    }

    public function linkageIfSingle()
    {
        return $this->linkageIf(function ($request) {
            return $request->getAttribute('jsonApiHandler') instanceof Show;
        });
    }

    public function noLinkage()
    {
        return $this->linkageIf(function () {
            return false;
        });
    }

    public function loadable()
    {
        $this->loadable = true;

        return $this;
    }

    public function notLoadable()
    {
        $this->loadable = false;

        return $this;
    }

    public function load(Closure $callback)
    {
        $this->loader = $callback;

        return $this;
    }

    public function included()
    {
        $this->included = true;

        return $this;
    }

    public function noLinks()
    {
        $this->hasLinks = false;

        return $this;
    }
}
