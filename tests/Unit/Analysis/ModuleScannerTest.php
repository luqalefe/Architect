<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Analysis;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Analysis\ImportExtractor;
use LaravelModulesArch\Analysis\ModuleScanner;
use LaravelModulesArch\Tests\TestCase;

class ModuleScannerTest extends TestCase
{
    private string $modulesPath;

    private Filesystem $files;

    private ModuleScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = sys_get_temp_dir().'/module-scanner-'.uniqid();
        $this->files->ensureDirectoryExists($this->modulesPath);
        $this->scanner = new ModuleScanner($this->files, new ImportExtractor);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_returns_empty_when_modules_path_does_not_exist(): void
    {
        $this->assertSame([], $this->scanner->scan('/no/such/path'));
    }

    public function test_returns_empty_when_no_modules_present(): void
    {
        $this->assertSame([], $this->scanner->scan($this->modulesPath));
    }

    public function test_discovers_files_across_all_modules(): void
    {
        $this->writeFile('Sale/Domain/Entities/SaleOrder.php', '<?php namespace Modules\\Sale\\Domain\\Entities; class SaleOrder {}');
        $this->writeFile('Crm/Domain/Entities/Lead.php', '<?php namespace Modules\\Crm\\Domain\\Entities; class Lead {}');

        $files = $this->scanner->scan($this->modulesPath);

        $this->assertCount(2, $files);
        $modules = array_map(fn ($f) => $f->module, $files);
        $this->assertContains('Sale', $modules);
        $this->assertContains('Crm', $modules);
    }

    public function test_only_module_filter_restricts_scan(): void
    {
        $this->writeFile('Sale/Domain/Entities/SaleOrder.php', '<?php namespace Modules\\Sale\\Domain\\Entities;');
        $this->writeFile('Crm/Domain/Entities/Lead.php', '<?php namespace Modules\\Crm\\Domain\\Entities;');

        $files = $this->scanner->scan($this->modulesPath, 'Sale');

        $this->assertCount(1, $files);
        $this->assertSame('Sale', $files[0]->module);
    }

    public function test_detects_layer_from_relative_path(): void
    {
        $this->writeFile('Sale/Domain/Entities/SaleOrder.php', '<?php namespace Modules\\Sale\\Domain\\Entities;');
        $this->writeFile('Sale/Application/Actions/CreateSaleOrder.php', '<?php namespace Modules\\Sale\\Application\\Actions;');
        $this->writeFile('Sale/Infrastructure/Http/Controllers/SaleOrderController.php', '<?php namespace Modules\\Sale\\Infrastructure\\Http\\Controllers;');
        $this->writeFile('Sale/Contracts/SaleOrderContract.php', '<?php namespace Modules\\Sale\\Contracts;');
        $this->writeFile('Sale/routes/web.php', '<?php');

        $files = $this->scanner->scan($this->modulesPath);
        $byPath = [];
        foreach ($files as $f) {
            $byPath[$f->relativePath] = $f->layer;
        }

        $this->assertSame('Domain', $byPath['Domain/Entities/SaleOrder.php']);
        $this->assertSame('Application', $byPath['Application/Actions/CreateSaleOrder.php']);
        $this->assertSame('Infrastructure', $byPath['Infrastructure/Http/Controllers/SaleOrderController.php']);
        $this->assertSame('Contracts', $byPath['Contracts/SaleOrderContract.php']);
        $this->assertSame('Other', $byPath['routes/web.php']);
    }

    public function test_carries_imports_into_module_file(): void
    {
        $this->writeFile('Sale/Application/Actions/CreateSaleOrder.php', <<<'PHP'
            <?php

            namespace Modules\Sale\Application\Actions;

            use Modules\Sale\Domain\Entities\SaleOrder;
            use Modules\Sale\Domain\Repositories\SaleOrderRepositoryInterface;

            class CreateSaleOrder {}
            PHP);

        $files = $this->scanner->scan($this->modulesPath);

        $this->assertCount(1, $files);
        $imports = $files[0]->imports;
        $namespaces = array_map(fn ($i) => $i->namespace, $imports);

        $this->assertContains('Modules\\Sale\\Domain\\Entities\\SaleOrder', $namespaces);
        $this->assertContains('Modules\\Sale\\Domain\\Repositories\\SaleOrderRepositoryInterface', $namespaces);
    }

    private function writeFile(string $relative, string $contents): void
    {
        $path = $this->modulesPath.'/'.$relative;
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
    }
}
