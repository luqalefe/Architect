<?php

declare(strict_types=1);

namespace LaravelModulesArch\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelModulesArch\Support\ModuleAwareRelation;

/**
 * The singular counterpart of {@see NullRelation}: stands in for hasOne,
 * belongsTo, morphOne and morphTo when the target module is disabled.
 *
 * The cardinality matters — Eloquent's singular relations resolve to
 * `Model|null`, so views and tightly-typed callers expect `null` here, not an
 * empty Collection.
 *
 * @extends Relation<Model, Model, Model|null>
 *
 * @see ModuleAwareRelation
 */
class NullSingularRelation extends Relation
{
    public function __construct(Model $parent)
    {
        parent::__construct($parent->newQuery(), $parent);
    }

    public function addConstraints(): void
    {
        // No constraints — there is no query to constrain.
    }

    /**
     * @param  array<int, Model>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        // No eager constraints — the relation never executes.
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, Model>
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * @param  array<int, Model>  $models
     * @param  Collection<int, Model>  $results
     * @return array<int, Model>
     */
    public function match(array $models, Collection $results, $relation): array
    {
        return $this->initRelation($models, $relation);
    }

    public function getResults(): ?Model
    {
        return null;
    }

    /**
     * Eloquent's eager-loading pipeline always works against a Collection
     * buffer, regardless of cardinality — match() is what assigns the
     * `Model|null` shape per parent.
     *
     * @return Collection<int, Model>
     */
    public function getEager(): Collection
    {
        return new Collection;
    }
}
