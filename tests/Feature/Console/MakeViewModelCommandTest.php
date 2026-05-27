<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeViewModelCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_view_model_at_expected_path(): void
    {
        $this->artisanPending('arch:make-view-model', ['name' => 'Sale/SaleOrderIndexViewModel'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Application/ViewModels/SaleOrderIndexViewModel.php');
    }

    public function test_generated_view_model_is_final_with_di_constructor(): void
    {
        $this->artisanPending('arch:make-view-model', ['name' => 'Sale/SaleOrderIndexViewModel'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Application/ViewModels/SaleOrderIndexViewModel.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Application\\ViewModels;', $contents);
        $this->assertStringContainsString('final class SaleOrderIndexViewModel', $contents);
        $this->assertStringContainsString('public function __construct(', $contents);
    }
}
