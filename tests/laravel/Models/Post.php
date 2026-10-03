<?php

namespace Tobyz\Tests\JsonApiServer\laravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $casts = ['published' => 'boolean', 'published_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function authorByEmail(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_email', 'email');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('published', true);
    }

    public function scopeTitled(Builder $query, string $title): void
    {
        $query->where('title', $title);
    }
}
