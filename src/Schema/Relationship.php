<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

abstract class Relationship extends Field
{
    use HasMeta;

    private $type;
    private $linkage = false;
    private $links = true;
    private $loadable = true;
    private $includable = false;

    public function getLocation(): string
    {
        return 'relationships';
    }

    /**
     * Set the resource type that this relationship is to.
     */
    public function type($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Define this as a polymorphic relationship.
     */
    public function polymorphic(array $types = null)
    {
        $this->type = $types;

        return $this;
    }

    /**
     * Show resource linkage for the relationship.
     */
    public function linkage()
    {
        $this->linkage = true;

        return $this;
    }

    /**
     * Do not show resource linkage for the relationship.
     */
    public function noLinkage()
    {
        $this->linkage = false;

        return $this;
    }

    /**
     * Allow the relationship data to be eager-loaded into the model collection.
     *
     * This is used to prevent the n+1 query problem. If null, the adapter will
     * be used to eager-load relationship data into the model collection.
     */
    public function loadable(callable $callback = null)
    {
        $this->loadable = $callback ?: true;

        return $this;
    }

    /**
     * Do not eager-load relationship data into the model collection.
     */
    public function notLoadable()
    {
        $this->loadable = false;

        return $this;
    }

    /**
     * Allow the relationship data to be included in a compound document.
     */
    public function includable()
    {
        $this->includable = true;

        return $this;
    }

    /**
     * Do not allow the relationship data to be included in a compound document.
     */
    public function notIncludable()
    {
        $this->includable = false;

        return $this;
    }

    /**
     * Show links for the relationship.
     */
    public function links()
    {
        $this->links = true;

        return $this;
    }

    /**
     * Do not show links for the relationship.
     */
    public function noLinks()
    {
        $this->links = false;

        return $this;
    }

    /**
     * Apply a scope to the query to eager-load the relationship data.
     */
    public function scope(callable $callback)
    {
        $this->listeners['scope'][] = $callback;
    }

    public function getType()
    {
        return $this->type;
    }

    public function isLinkage(): bool
    {
        return $this->linkage;
    }

    public function isLinks(): bool
    {
        return $this->links;
    }

    /**
     * @return bool|callable
     */
    public function isLoadable()
    {
        return $this->loadable;
    }

    public function isIncludable(): bool
    {
        return $this->includable;
    }
}
