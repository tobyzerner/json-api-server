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
    // private $urls = true;
    private $load = true;
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
    public function withLinkage()
    {
        $this->linkage = true;

        return $this;
    }

    /**
     * Do not show resource linkage for the relationship.
     */
    public function withoutLinkage()
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
    public function load(callable $callback = null)
    {
        $this->load = $callback ?: true;

        return $this;
    }

    /**
     * Do not eager-load relationship data into the model collection.
     */
    public function dontLoad()
    {
        $this->load = false;

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

    // /**
    //  * Make URLs available for the relationship.
    //  */
    // public function withUrls()
    // {
    //     $this->urls = true;
    //
    //     return $this;
    // }
    //
    // /**
    //  * Do not make URLs avaialble for the relationship.
    //  */
    // public function withoutUrls()
    // {
    //     $this->urls = false;
    //
    //     return $this;
    // }

    /**
     * Apply a scope to the query to eager-load the relationship data.
     */
    public function scope(callable $callback)
    {
        $this->listeners['scope'][] = $callback;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function hasLinkage(): bool
    {
        return $this->linkage;
    }

    // public function hasUrls(): bool
    // {
    //     return $this->urls;
    // }

    /**
     * @return bool|callable
     */
    public function getLoad()
    {
        return $this->load;
    }

    public function isIncludable(): bool
    {
        return $this->includable;
    }
}
