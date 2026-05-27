<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Testing;

use LaravelModulesArch\Testing\ModuleIsolation;
use LaravelModulesArch\Tests\TestCase;
use Mockery;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;

class ModuleIsolationTest extends TestCase
{
    public function test_apply_enables_only_listed_modules_and_disables_the_rest(): void
    {
        $sale = $this->fakeModule('Sale', enabled: true);
        $crm = $this->fakeModule('Crm', enabled: true);

        $repo = Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive('all')->andReturn(['Sale' => $sale, 'Crm' => $crm]);

        $previous = ModuleIsolation::apply($repo, ['Sale']);

        $this->assertSame(['Sale' => true, 'Crm' => true], $previous);
        $this->assertTrue($sale->isEnabled());
        $this->assertFalse($crm->isEnabled());
    }

    public function test_apply_enables_module_that_was_disabled_if_it_is_listed(): void
    {
        $sale = $this->fakeModule('Sale', enabled: false);

        $repo = Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive('all')->andReturn(['Sale' => $sale]);

        $previous = ModuleIsolation::apply($repo, ['Sale']);

        $this->assertSame(['Sale' => false], $previous);
        $this->assertTrue($sale->isEnabled(), 'apply() must turn a disabled-but-listed module on.');
    }

    public function test_apply_with_empty_list_disables_everything(): void
    {
        $sale = $this->fakeModule('Sale', enabled: true);
        $crm = $this->fakeModule('Crm', enabled: true);

        $repo = Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive('all')->andReturn(['Sale' => $sale, 'Crm' => $crm]);

        ModuleIsolation::apply($repo, []);

        $this->assertFalse($sale->isEnabled());
        $this->assertFalse($crm->isEnabled());
    }

    public function test_restore_re_enables_modules_that_were_originally_enabled(): void
    {
        $sale = $this->fakeModule('Sale', enabled: false);
        $crm = $this->fakeModule('Crm', enabled: true);

        $repo = Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive('find')->with('Sale')->andReturn($sale);
        $repo->shouldReceive('find')->with('Crm')->andReturn($crm);

        ModuleIsolation::restore($repo, ['Sale' => true, 'Crm' => false]);

        $this->assertTrue($sale->isEnabled(), 'Sale was originally enabled, restore must re-enable it.');
        $this->assertFalse($crm->isEnabled(), 'Crm was originally disabled, restore must disable it.');
    }

    public function test_restore_skips_modules_that_are_no_longer_in_the_repository(): void
    {
        $this->expectNotToPerformAssertions();

        $repo = Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive('find')->with('Ghost')->andReturn(null);

        ModuleIsolation::restore($repo, ['Ghost' => true]);
    }

    private function fakeModule(string $name, bool $enabled): FakeIsolationModule
    {
        return new FakeIsolationModule($name, $enabled);
    }
}

/**
 * @internal Concrete-but-inert Module subclass used only inside isolation
 * tests. Deliberately skips the parent constructor (which would resolve
 * Nwidart\Modules\Contracts\ActivatorInterface from the container — not
 * registered in our standalone Testbench app).
 */
class FakeIsolationModule extends Module
{
    public function __construct(string $name, private bool $enabled = true)
    {
        // Intentionally NO parent::__construct — see class docblock.
        $this->name = $name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function registerAliases(): void {}

    public function registerProviders(): void {}

    public function getCachedServicesPath(): string
    {
        return '';
    }
}
