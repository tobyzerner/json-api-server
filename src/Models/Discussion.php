<?php

namespace Tobscure\JsonApiServer\Models;

use Illuminate\Database\Eloquent\Model;

class Discussion extends Model
{
    protected $dates = ['created_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
