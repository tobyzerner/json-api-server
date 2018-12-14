<?php

namespace Tobscure\JsonApiServer\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $dates = ['created_at', 'edited_at', 'hidden_at'];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function editedUser()
    {
        return $this->belongsTo(User::class, 'edited_user_id');
    }
}
