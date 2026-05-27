<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeStateCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_state_at_expected_enum_path(): void
    {
        $this->artisanPending('arch:make-state', ['name' => 'Sale/OrderStatus'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Domain/Enums/OrderStatus.php');
    }

    public function test_generated_state_has_throwing_transition_to_and_can_transition_to(): void
    {
        $this->artisanPending('arch:make-state', ['name' => 'Sale/OrderStatus'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Domain/Enums/OrderStatus.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Domain\\Enums;', $contents);
        $this->assertStringContainsString('use DomainException;', $contents);
        $this->assertStringContainsString('enum OrderStatus: string', $contents);
        $this->assertStringContainsString('public function canTransitionTo(self $target): bool', $contents);
        $this->assertStringContainsString('public function transitionTo(self $target): self', $contents);
        $this->assertStringContainsString('throw new DomainException', $contents);
    }
}
