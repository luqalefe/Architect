<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Support\ModuleJsonSchema;
use LaravelModulesArch\Tests\TestCase;

class MakeModuleCommandTest extends TestCase
{
    private string $tempModulesPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tempModulesPath = sys_get_temp_dir().'/modules-arch-'.uniqid();
        $this->files->ensureDirectoryExists($this->tempModulesPath);

        config(['modules-arch.modules_path' => $this->tempModulesPath]);
    }

    protected function tearDown(): void
    {
        if ($this->files->exists($this->tempModulesPath)) {
            $this->files->deleteDirectory($this->tempModulesPath);
        }

        parent::tearDown();
    }

    public function test_pragmatic_mode_creates_full_layout_without_pure_domain_folders(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $modulePath = $this->tempModulesPath.'/Sale';
        $this->assertFileExists($modulePath.'/module.json');
        $this->assertFileExists($modulePath.'/composer.json');
        $this->assertFileExists($modulePath.'/Infrastructure/Providers/SaleServiceProvider.php');
        $this->assertFileExists($modulePath.'/routes/web.php');
        $this->assertFileExists($modulePath.'/routes/api.php');

        $this->assertDirectoryExists($modulePath.'/Contracts');
        $this->assertDirectoryExists($modulePath.'/Domain/Enums');
        $this->assertDirectoryExists($modulePath.'/Application/Actions');
        $this->assertDirectoryExists($modulePath.'/Infrastructure/ACL');
        $this->assertDirectoryExists($modulePath.'/database/migrations');
        $this->assertDirectoryExists($modulePath.'/tests/Unit/Domain');

        $this->assertDirectoryDoesNotExist($modulePath.'/Domain/Entities');
        $this->assertDirectoryDoesNotExist($modulePath.'/Domain/ValueObjects');
        $this->assertDirectoryDoesNotExist($modulePath.'/Domain/Repositories');
        $this->assertDirectoryDoesNotExist($modulePath.'/Infrastructure/Persistence/Repositories');
    }

    public function test_purist_mode_includes_pure_domain_folders(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale', '--mode' => 'purist'])->assertSuccessful();

        $modulePath = $this->tempModulesPath.'/Sale';
        $this->assertDirectoryExists($modulePath.'/Domain/Entities');
        $this->assertDirectoryExists($modulePath.'/Domain/ValueObjects');
        $this->assertDirectoryExists($modulePath.'/Domain/Repositories');
        $this->assertDirectoryExists($modulePath.'/Infrastructure/Persistence/Repositories');
        $this->assertFileExists($modulePath.'/Domain/Entities/.gitkeep');
    }

    public function test_generated_module_json_validates_against_the_schema(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $manifest = json_decode($this->files->get($this->tempModulesPath.'/Sale/module.json'), true);
        $this->assertIsArray($manifest);

        $result = (new ModuleJsonSchema)->validate($manifest);
        $this->assertTrue($result->isValid(), $result->summary());

        $this->assertSame('Sale', $manifest['name']);
        $this->assertSame('sale', $manifest['alias']);
        $this->assertSame('Modules\\Sale\\Infrastructure\\Providers\\SaleServiceProvider', $manifest['providers'][0]);
    }

    public function test_generated_service_provider_has_correct_namespace_and_uses_trait(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $contents = $this->files->get($this->tempModulesPath.'/Sale/Infrastructure/Providers/SaleServiceProvider.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Infrastructure\\Providers;', $contents);
        $this->assertStringContainsString('use LaravelModulesArch\\Concerns\\RegistersModuleMorphMap;', $contents);
        $this->assertStringContainsString('class SaleServiceProvider extends ServiceProvider', $contents);
        $this->assertStringContainsString('use RegistersModuleMorphMap;', $contents);
    }

    public function test_multi_word_module_names_produce_snake_case_alias(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'CustomerSupport'])->assertSuccessful();

        $manifest = json_decode($this->files->get($this->tempModulesPath.'/CustomerSupport/module.json'), true);
        $this->assertSame('customer_support', $manifest['alias']);
    }

    public function test_refuses_invalid_module_name(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'sale_order'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->tempModulesPath.'/sale_order');
    }

    public function test_refuses_invalid_mode(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale', '--mode' => 'weird'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->tempModulesPath.'/Sale');
    }

    public function test_refuses_to_overwrite_existing_module_without_force(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertFailed();
    }

    public function test_force_flag_overwrites_existing_module(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $this->files->put($this->tempModulesPath.'/Sale/module.json', '{"name": "tampered"}');

        $this->artisanPending('arch:make-module', ['name' => 'Sale', '--force' => true])->assertSuccessful();

        $manifest = json_decode($this->files->get($this->tempModulesPath.'/Sale/module.json'), true);
        $this->assertSame('Sale', $manifest['name']);
    }
}
