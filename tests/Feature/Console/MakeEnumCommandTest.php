<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeEnumCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_enum_at_expected_path(): void
    {
        $this->artisanPending('arch:make-enum', ['name' => 'Sale/OrderStatus'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Domain/Enums/OrderStatus.php');
    }

    public function test_generated_enum_is_backed_and_has_can_transition_to(): void
    {
        $this->artisanPending('arch:make-enum', ['name' => 'Sale/OrderStatus'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Domain/Enums/OrderStatus.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Domain\\Enums;', $contents);
        $this->assertStringContainsString('enum OrderStatus: string', $contents);
        $this->assertStringContainsString('public function canTransitionTo(self $target): bool', $contents);
    }
}
