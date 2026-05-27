<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Tests\TestCase;

class MakeRepositoryCommandTest extends TestCase
{
    private string $modulesPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = sys_get_temp_dir().'/modules-arch-'.uniqid();
        $this->files->ensureDirectoryExists($this->modulesPath);

        config(['modules-arch.modules_path' => $this->modulesPath]);

        // Bootstrap a real Sale module so the ServiceProvider exists and the
        // wiring step has something to edit.
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        if ($this->files->exists($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    public function test_creates_both_interface_and_eloquent_implementation_files(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Domain/Repositories/SaleOrderRepositoryInterface.php');
        $this->assertFileExists($this->modulesPath.'/Sale/Infrastructure/Persistence/Repositories/EloquentSaleOrderRepository.php');
    }

    public function test_interface_has_expected_namespace_and_methods(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Domain/Repositories/SaleOrderRepositoryInterface.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Domain\\Repositories;', $contents);
        $this->assertStringContainsString('use Modules\\Sale\\Domain\\Entities\\SaleOrder;', $contents);
        $this->assertStringContainsString('interface SaleOrderRepositoryInterface', $contents);
        $this->assertStringContainsString('public function findById(string $id): ?SaleOrder;', $contents);
        $this->assertStringContainsString('public function save(SaleOrder $entity): void;', $contents);
        $this->assertStringContainsString('public function delete(string $id): void;', $contents);
    }

    public function test_eloquent_implementation_implements_interface_and_uses_correct_aliases(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Infrastructure/Persistence/Repositories/EloquentSaleOrderRepository.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Infrastructure\\Persistence\\Repositories;', $contents);
        $this->assertStringContainsString('use Modules\\Sale\\Domain\\Entities\\SaleOrder as SaleOrderEntity;', $contents);
        $this->assertStringContainsString('use Modules\\Sale\\Domain\\Repositories\\SaleOrderRepositoryInterface;', $contents);
        $this->assertStringContainsString('use Modules\\Sale\\Infrastructure\\Persistence\\Models\\SaleOrder as SaleOrderModel;', $contents);
        $this->assertStringContainsString('final class EloquentSaleOrderRepository implements SaleOrderRepositoryInterface', $contents);
    }

    public function test_service_provider_binding_is_added_between_markers(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Infrastructure/Providers/SaleServiceProvider.php');

        $this->assertStringContainsString(
            '$this->app->bind(',
            $contents,
        );
        $this->assertStringContainsString(
            '\\Modules\\Sale\\Domain\\Repositories\\SaleOrderRepositoryInterface::class,',
            $contents,
        );
        $this->assertStringContainsString(
            '\\Modules\\Sale\\Infrastructure\\Persistence\\Repositories\\EloquentSaleOrderRepository::class,',
            $contents,
        );

        // Binding must land before the end marker so future bindings stack
        // above it, not below.
        $bindingPos = strpos($contents, 'SaleOrderRepositoryInterface::class');
        $markerPos = strpos($contents, '// @arch-bindings-end');
        $this->assertIsInt($bindingPos);
        $this->assertIsInt($markerPos);
        $this->assertLessThan($markerPos, $bindingPos);
    }

    public function test_re_running_with_force_does_not_duplicate_binding(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertSuccessful();
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder', '--force' => true])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Infrastructure/Providers/SaleServiceProvider.php');
        $occurrences = substr_count($contents, 'SaleOrderRepositoryInterface::class');
        $this->assertSame(1, $occurrences, 'Binding for SaleOrderRepositoryInterface must appear exactly once.');
    }

    public function test_multiple_repositories_in_the_same_module_stack_above_end_marker(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertSuccessful();
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleInvoice'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Infrastructure/Providers/SaleServiceProvider.php');
        $this->assertStringContainsString('SaleOrderRepositoryInterface::class', $contents);
        $this->assertStringContainsString('SaleInvoiceRepositoryInterface::class', $contents);

        $orderPos = strpos($contents, 'SaleOrderRepositoryInterface::class');
        $invoicePos = strpos($contents, 'SaleInvoiceRepositoryInterface::class');
        $markerPos = strpos($contents, '// @arch-bindings-end');
        $this->assertIsInt($orderPos);
        $this->assertIsInt($invoicePos);
        $this->assertIsInt($markerPos);
        $this->assertLessThan($markerPos, $orderPos);
        $this->assertLessThan($markerPos, $invoicePos);
    }

    public function test_refuses_without_module(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Ghost/SaleOrder'])->assertFailed();
    }

    public function test_refuses_to_overwrite_without_force(): void
    {
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertSuccessful();
        $this->artisanPending('arch:make-repository', ['name' => 'Sale/SaleOrder'])->assertFailed();
    }
}
