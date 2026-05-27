<?php

declare(strict_types=1);

namespace LaravelModulesArch\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelModulesArch\Support\ModuleAwareRelation;

/**
 * A real Eloquent relation that never touches the database.
 *
 * Used as the no-op stand-in returned by {@see ModuleAwareRelation}
 * when the target module of a relation is disabled. Implements every abstract
 * method of {@see Relation} so that eager loading, lazy access and serialization
 * keep working without any SQL being issued.
 *
 * @extends Relation<Model, Model, Collection<int, Model>>
 *
 * @see ModuleAwareRelation
 */
class NullRelation extends Relation
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
            $model->setRelation($relation, new Collection);
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

    /**
     * @return Collection<int, Model>
     */
    public function getResults(): Collection
    {
        return new Collection;
    }

    /**
     * Eloquent calls this during eager loading to fetch the related rows.
     * We short-circuit it to skip the SQL query entirely.
     *
     * @return Collection<int, Model>
     */
    public function getEager(): Collection
    {
        return new Collection;
    }
}
