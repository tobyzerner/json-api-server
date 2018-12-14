<?php

namespace Tobscure\JsonApiServer\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $dates = ['joined_at'];

    public $timestamps = false;

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }
}
