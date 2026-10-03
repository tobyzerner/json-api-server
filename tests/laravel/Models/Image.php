<?php

namespace Tobyz\Tests\JsonApiServer\laravel\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
