<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Testing;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Testing\Concerns\AssertsModuleBoundaries;
use LaravelModulesArch\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

class AssertsModuleBoundariesTest extends TestCase
{
    use AssertsModuleBoundaries;

    private string $modulesPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = sys_get_temp_dir().'/asserts-boundaries-'.uniqid();
        $this->files->ensureDirectoryExists($this->modulesPath);

        config(['modules-arch.modules_path' => $this->modulesPath]);
        config(['modules-arch.boundaries.enabled' => true]);
        config(['modules-arch.boundaries.rules' => [
            'cross_module_via_contracts' => true,
            'domain_no_infrastructure' => true,
            'domain_no_application' => true,
            'application_no_infrastructure' => false,
            'declared_subscriptions' => true,
            'aggregate_root_repositories' => true,
        ]]);
        config(['modules-arch.boundaries.ignored_namespaces' => [
            'Illuminate\\Support\\',
            'Illuminate\\Contracts\\',
        ]]);
    }

    protected function tearDown(): void
    {
        if ($this->files->exists($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    public function test_passes_for_a_clean_module(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $this->assertModuleHasNoBoundaryErrors();
    }

    public function test_passes_when_only_warnings_are_present(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->files->ensureDirectoryExists($this->modulesPath.'/Sale/Domain/Repositories');
        $this->files->put(
            $this->modulesPath.'/Sale/Domain/Repositories/SaleOrderItemRepositoryInterface.php',
            "<?php\nnamespace Modules\\Sale\\Domain\\Repositories;\ninterface SaleOrderItemRepositoryInterface {}\n",
        );

        $this->assertModuleHasNoBoundaryErrors();
    }

    public function test_fails_with_helpful_message_when_errors_are_present(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Crm'])->assertSuccessful();

        $this->files->put(
            $this->modulesPath.'/Sale/Application/Actions/Bad.php',
            "<?php\nnamespace Modules\\Sale\\Application\\Actions;\nuse Modules\\Crm\\Domain\\Entities\\Lead;\nfinal class Bad {}\n",
        );

        try {
            $this->assertModuleHasNoBoundaryErrors();
            $this->fail('Expected AssertionFailedError but assertion passed.');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('[R1]', $e->getMessage());
            $this->assertStringContainsString('Bad.php', $e->getMessage());
        }
    }

    public function test_module_filter_scopes_the_check(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Crm'])->assertSuccessful();

        // Plant the violation in Crm; assert Sale alone is clean
        $this->files->put(
            $this->modulesPath.'/Crm/Application/Actions/Bad.php',
            "<?php\nnamespace Modules\\Crm\\Application\\Actions;\nuse Modules\\Sale\\Domain\\Entities\\Order;\nfinal class Bad {}\n",
        );

        $this->assertModuleHasNoBoundaryErrors('Sale');
    }
}
