<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelModulesArch\Relations\NullRelation;
use LaravelModulesArch\Relations\NullSingularRelation;
use Nwidart\Modules\Facades\Module;
use Throwable;

/**
 * Builds an Eloquent relation that gracefully collapses to a {@see NullRelation}
 * (plural) or {@see NullSingularRelation} (singular) when the target module is
 * disabled (or not registered at all).
 *
 * Encapsulates the recurring "if module enabled then hasMany else NullRelation"
 * pattern that would otherwise be repeated on every cross-module relationship.
 *
 * @see NullRelation
 * @see NullSingularRelation
 */
final class ModuleAwareRelation
{
    /**
     * @param  class-string<Model>  $related
     * @return Relation<Model, Model, mixed>
     */
    public static function hasMany(
        Model $parent,
        string $module,
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
    ): Relation {
        if (! self::moduleEnabled($module)) {
            return new NullRelation($parent);
        }

        return $parent->hasMany($related, $foreignKey, $localKey);
    }

    /**
     * @param  class-string<Model>  $related
     * @return Relation<Model, Model, mixed>
     */
    public static function hasOne(
        Model $parent,
        string $module,
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
    ): Relation {
        if (! self::moduleEnabled($module)) {
            return new NullSingularRelation($parent);
        }

        return $parent->hasOne($related, $foreignKey, $localKey);
    }

    /**
     * @param  class-string<Model>  $related
     * @return Relation<Model, Model, mixed>
     */
    public static function belongsTo(
        Model $parent,
        string $module,
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null,
        ?string $relation = null,
    ): Relation {
        if (! self::moduleEnabled($module)) {
            return new NullSingularRelation($parent);
        }

        return $parent->belongsTo($related, $foreignKey, $ownerKey, $relation);
    }

    /**
     * @param  class-string<Model>  $related
     * @return Relation<Model, Model, mixed>
     */
    public static function morphMany(
        Model $parent,
        string $module,
        string $related,
        string $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null,
    ): Relation {
        if (! self::moduleEnabled($module)) {
            return new NullRelation($parent);
        }

        return $parent->morphMany($related, $name, $type, $id, $localKey);
    }

    /**
     * @param  class-string<Model>  $related
     * @return Relation<Model, Model, mixed>
     */
    public static function morphOne(
        Model $parent,
        string $module,
        string $related,
        string $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null,
    ): Relation {
        if (! self::moduleEnabled($module)) {
            return new NullSingularRelation($parent);
        }

        return $parent->morphOne($related, $name, $type, $id, $localKey);
    }

    /**
     * @return Relation<Model, Model, mixed>
     */
    public static function morphTo(
        Model $parent,
        string $module,
        ?string $name = null,
        ?string $type = null,
        ?string $id = null,
        ?string $ownerKey = null,
    ): Relation {
        if (! self::moduleEnabled($module)) {
            return new NullSingularRelation($parent);
        }

        return $parent->morphTo($name, $type, $id, $ownerKey);
    }

    /**
     * Defensive check: treats unknown / unregistered modules as disabled.
     * nwidart's repository throws ModuleNotFoundException for names it doesn't
     * recognize, which would surface as a hard crash on relation access.
     */
    private static function moduleEnabled(string $name): bool
    {
        try {
            return Module::isEnabled($name);
        } catch (Throwable) {
            return false;
        }
    }
}
