<?php

declare(strict_types=1);

namespace LaravelModulesArch\Testing;

use Nwidart\Modules\Contracts\RepositoryInterface;
use Orchestra\Testbench\TestCase;

/**
 * Base test case for tests that should run with only a specific subset of
 * modules enabled. Disables every module not declared in $enabledModules
 * during setUp, restores the previous state on tearDown.
 *
 * Real isolate/restore work lives in {@see ModuleIsolation} so the behaviour
 * is testable independently of PHPUnit's lifecycle.
 *
 * @example
 *  class SaleModuleTest extends IsolatedModuleTest
 *  {
 *      protected array $enabledModules = ['Base', 'Sale'];
 *
 *      public function test_sale_can_run_without_crm(): void { ... }
 *  }
 */
abstract class IsolatedModuleTest extends TestCase
{
    /** @var list<string> */
    protected array $enabledModules = [];

    /** @var array<string, bool> */
    private array $isolatedPreviousState = [];

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->modulesRepository();
        if ($repository !== null) {
            $this->isolatedPreviousState = ModuleIsolation::apply($repository, $this->enabledModules);
        }
    }

    protected function tearDown(): void
    {
        $repository = $this->modulesRepository();
        if ($repository !== null) {
            ModuleIsolation::restore($repository, $this->isolatedPreviousState);
        }
        $this->isolatedPreviousState = [];

        parent::tearDown();
    }

    private function modulesRepository(): ?RepositoryInterface
    {
        if ($this->app === null || ! $this->app->bound('modules')) {
            return null;
        }

        try {
            $repository = $this->app->make('modules');
        } catch (\Throwable) {
            return null;
        }

        return $repository;
    }
}
