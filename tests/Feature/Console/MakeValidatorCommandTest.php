<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeValidatorCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_validator_with_rules_suffix(): void
    {
        $this->artisanPending('arch:make-validator', ['name' => 'Sale/CreateSaleOrder'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Application/Validators/CreateSaleOrderRules.php');
        $this->assertFileDoesNotExist($this->modulesPath.'/Sale/Application/Validators/CreateSaleOrder.php');
    }

    public function test_generated_validator_exposes_static_rules_array(): void
    {
        $this->artisanPending('arch:make-validator', ['name' => 'Sale/CreateSaleOrder'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Application/Validators/CreateSaleOrderRules.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Application\\Validators;', $contents);
        $this->assertStringContainsString('final class CreateSaleOrderRules', $contents);
        $this->assertStringContainsString('public static function rules(): array', $contents);
    }
}
