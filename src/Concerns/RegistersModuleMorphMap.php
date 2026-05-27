<?php

declare(strict_types=1);

namespace LaravelModulesArch\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Trait for a module's ServiceProvider so each Bounded Context owns its own
 * morph map slice. Calls {@see Relation::morphMap()} in its default additive
 * mode (merge = true) so registrations from other modules survive.
 *
 * This avoids the "God Provider" anti-pattern where a single Base module has
 * to know every other module's morph aliases.
 *
 * @example
 *  class SaleServiceProvider extends ServiceProvider
 *  {
 *      use RegistersModuleMorphMap;
 *
 *      protected function morphMap(): array
 *      {
 *          return ['sale_order' => SaleOrder::class];
 *      }
 *
 *      public function boot(): void
 *      {
 *          $this->bootModuleMorphMap();
 *      }
 *  }
 */
trait RegistersModuleMorphMap
{
    /**
     * Morph aliases owned by this module.
     *
     * @return array<string, class-string<Model>>
     */
    abstract protected function morphMap(): array;

    /**
     * Register this module's morph aliases additively, leaving any aliases
     * already registered by other modules untouched.
     */
    protected function bootModuleMorphMap(): void
    {
        Relation::morphMap($this->morphMap());
    }
}
