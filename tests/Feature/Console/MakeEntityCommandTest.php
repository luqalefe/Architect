<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeEntityCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_entity_at_expected_path(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'Sale/SaleOrder'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Domain/Entities/SaleOrder.php');
    }

    public function test_generated_entity_has_correct_namespace_and_skeleton(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'Sale/SaleOrder'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Domain/Entities/SaleOrder.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Domain\\Entities;', $contents);
        $this->assertStringContainsString('final class SaleOrder', $contents);
        $this->assertStringContainsString('public function pullDomainEvents(): array', $contents);
        $this->assertStringContainsString('private function recordEvent(object $event): void', $contents);
        $this->assertStringContainsString('private readonly string $id', $contents);
    }

    public function test_refuses_argument_without_slash(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'SaleOrder'])->assertFailed();

        $this->assertFileDoesNotExist($this->modulesPath.'/Sale/Domain/Entities/SaleOrder.php');
    }

    public function test_refuses_module_that_does_not_exist(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'NotAModule/SaleOrder'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/NotAModule');
    }

    public function test_refuses_non_studly_module_name(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'sale/SaleOrder'])->assertFailed();
    }

    public function test_refuses_non_studly_artifact_name(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'Sale/sale_order'])->assertFailed();
    }

    public function test_refuses_to_overwrite_without_force(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'Sale/SaleOrder'])->assertSuccessful();
        $this->artisanPending('arch:make-entity', ['name' => 'Sale/SaleOrder'])->assertFailed();
    }

    public function test_force_overwrites(): void
    {
        $this->artisanPending('arch:make-entity', ['name' => 'Sale/SaleOrder'])->assertSuccessful();

        $path = $this->modulesPath.'/Sale/Domain/Entities/SaleOrder.php';
        $this->files->put($path, '// tampered');

        $this->artisanPending('arch:make-entity', ['name' => 'Sale/SaleOrder', '--force' => true])->assertSuccessful();

        $this->assertStringContainsString('final class SaleOrder', $this->files->get($path));
    }
}
