<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeActionCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_action_at_expected_path(): void
    {
        $this->artisanPending('arch:make-action', ['name' => 'Sale/CreateSaleOrder'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Application/Actions/CreateSaleOrder.php');
    }

    public function test_generated_action_has_handle_method_and_constructor_for_di(): void
    {
        $this->artisanPending('arch:make-action', ['name' => 'Sale/CreateSaleOrder'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Application/Actions/CreateSaleOrder.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Application\\Actions;', $contents);
        $this->assertStringContainsString('final class CreateSaleOrder', $contents);
        $this->assertStringContainsString('public function __construct(', $contents);
        $this->assertStringContainsString('public function handle(', $contents);
    }
}
