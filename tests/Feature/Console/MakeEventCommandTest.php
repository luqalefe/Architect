<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeEventCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_event_at_expected_path(): void
    {
        $this->artisanPending('arch:make-event', ['name' => 'Sale/OrderItemAdded'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Domain/Events/OrderItemAdded.php');
    }

    public function test_generated_event_is_final_readonly_popo_and_module_aware(): void
    {
        $this->artisanPending('arch:make-event', ['name' => 'Sale/OrderItemAdded'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Domain/Events/OrderItemAdded.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Domain\\Events;', $contents);
        $this->assertStringContainsString('final readonly class OrderItemAdded', $contents);
        $this->assertStringContainsString('Sale bounded context', $contents);
        $this->assertStringNotContainsString('use Illuminate\\', $contents);
    }
}
