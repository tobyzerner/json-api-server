<?php

namespace Tobyz\JsonApiServer\Laravel\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Schema\Field\ToOne as BaseToOne;

class ToOne extends BaseToOne
{
    use Concerns\ScopesRelationship;

    public bool $foreignKeyLinkage = false;

    /**
     * Include linkage for this relationship, optionally under a condition,
     * built from the foreign key of a belongs-to relation when only linkage is
     * needed, rather than by loading the related model through its scopes.
     */
    public function withForeignKeyLinkage(bool|Closure $condition = true): static
    {
        $this->foreignKeyLinkage = $condition !== false;

        return $this->withLinkage($condition);
    }

    public function getValue(Context $context): mixed
    {
        $model = $context->model;

        if (
            !$this->foreignKeyLinkage ||
            $this->getter ||
            !$context->linkageOnly ||
            !$model instanceof Model
        ) {
            return parent::getValue($context);
        }

        if (!$this->hasLinkage($context)) {
            return null;
        }

        $method = $this->property ?: $this->name;

        if (!$model->isRelation($method)) {
            return parent::getValue($context);
        }

        $relation = $model->$method();

        if (!$relation instanceof BelongsTo) {
            return parent::getValue($context);
        }

        if ($model->relationLoaded($method)) {
            return $model->getRelation($method);
        }

        $foreignKey = $model->getAttribute($relation->getForeignKeyName());

        // Without a morph type, a morph-to relation's related model is the
        // parent model itself, so there's nothing to link to.
        if (
            $foreignKey === null ||
            ($relation instanceof MorphTo && !$model->getAttribute($relation->getMorphType()))
        ) {
            return null;
        }

        // The relation creates a fresh related model each time it's built.
        $related = $relation->getRelated();
        $ownerKey = $relation->getOwnerKeyName() ?: $related->getKeyName();
        $related->forceFill([$ownerKey => $foreignKey]);

        // The foreign key is only the related resource's ID if it references
        // the column the ID reads; otherwise, load it normally.
        $resource = $context->resourceForModel($this->collections, $related);

        if (!$resource instanceof EloquentResource || $resource->idColumn($related) !== $ownerKey) {
            return parent::getValue($context);
        }

        return $related;
    }
}
