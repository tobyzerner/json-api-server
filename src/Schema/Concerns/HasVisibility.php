<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Schema\Concerns;

use function Tobyz\JsonApiServer\negate;

trait HasVisibility
{
    private $visible = true;

    /**
     * Allow this field to be seen.
     */
    public function visible(callable $condition = null)
    {
        $this->visible = $condition ?: true;

        return $this;
    }

    /**
     * Disallow this field to be seen.
     */
    public function hidden(callable $condition = null)
    {
        $this->visible = $condition ? negate($condition) : false;

        return $this;
    }

    public function getVisible()
    {
        return $this->visible;
    }
}
