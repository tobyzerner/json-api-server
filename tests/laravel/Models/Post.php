<?php

namespace Tobyz\Tests\JsonApiServer\laravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
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

    /**
     * The author, unless the post is a draft. The relation reads its foreign
     * key conditionally, like a belongs-to over one type of a morph key.
     */
    public function publishedAuthor(): BelongsTo
    {
        return new class(User::query(), $this, 'user_id', 'id', 'publishedAuthor') extends
            BelongsTo {
            protected function getForeignKeyFrom(EloquentModel $model)
            {
                return $model->title === 'Draft' ? null : parent::getForeignKeyFrom($model);
            }
        };
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
