<?php

/*
 * This file is part of Forust.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\Schema\Meta;

trait HasMeta
{
    private $meta = [];

    /**
     * Add a meta attribute.
     */
    public function meta(string $name, callable $value): Meta
    {
        return $this->meta[$name] = new Meta($name, $value);
    }

    /**
     * Remove a meta attribute.
     */
    public function removeMeta(string $name): void
    {
        unset($this->meta[$name]);
    }

    /**
     * Get the meta attributes.
     *
     * @return Meta[]
     */
    public function getMeta(): array
    {
        return $this->meta;
    }
}
