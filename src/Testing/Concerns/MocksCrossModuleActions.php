<?php

declare(strict_types=1);

namespace LaravelModulesArch\Testing\Concerns;

use Illuminate\Foundation\Application;
use LaravelModulesArch\Support\CrossModuleAction;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Test trait that lets a feature test stub a {@see CrossModuleAction}
 * call without having the target module actually enabled or even installed.
 *
 * Two effects per `fakeCrossModule()` call:
 *   1. The Module facade is taught that the target module is enabled (so the
 *      `Module::isEnabled()` guard inside CrossModuleAction::run passes).
 *   2. The target action class is bound in the container to a stub whose
 *      `handle()` returns the value supplied in the test.
 *
 * The action FQCN must autoload (real class) — the stub only swaps the
 * implementation, not the class definition.
 *
 * @property Application|null $app
 */
trait MocksCrossModuleActions
{
    protected function fakeCrossModule(string $module, string $action, mixed $returnValue): void
    {
        $app = $this->bootedApp();

        $this->ensureModuleEnabled($app, $module);

        $app->bind($action, fn () => new class($returnValue)
        {
            public function __construct(private readonly mixed $returnValue) {}

            public function handle(mixed ...$args): mixed
            {
                return $this->returnValue;
            }
        });
    }

    private function ensureModuleEnabled(Application $app, string $module): void
    {
        if (! $app->bound('modules')) {
            $app->instance('modules', Mockery::mock());
        }

        $modules = $app->make('modules');
        if ($modules instanceof MockInterface) {
            $modules->shouldReceive('isEnabled')->with($module)->andReturn(true);
        }
    }

    private function bootedApp(): Application
    {
        if ($this->app === null) {
            throw new RuntimeException(
                'MocksCrossModuleActions requires a booted application — hook it into a Laravel/Testbench TestCase.'
            );
        }

        return $this->app;
    }
}
