<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeIntegrationEventCommandTest extends ArtifactCommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->files->deleteDirectory($this->modulesPath.'/Sale');
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
    }

    public function test_creates_integration_event_in_contracts_events_folder(): void
    {
        $this->artisanPending('arch:make-integration-event', ['name' => 'Sale/SaleOrderCompleted'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Contracts/Events/SaleOrderCompleted.php');
    }

    public function test_generated_event_is_final_readonly_popo(): void
    {
        $this->artisanPending('arch:make-integration-event', ['name' => 'Sale/SaleOrderCompleted'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Contracts/Events/SaleOrderCompleted.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Contracts\\Events;', $contents);
        $this->assertStringContainsString('final readonly class SaleOrderCompleted', $contents);
        $this->assertStringNotContainsString('use Illuminate\\', $contents);
    }

    public function test_module_json_lists_the_event_in_events_publishes(): void
    {
        $this->artisanPending('arch:make-integration-event', ['name' => 'Sale/SaleOrderCompleted'])->assertSuccessful();

        $manifest = json_decode($this->files->get($this->modulesPath.'/Sale/module.json'), true);
        $this->assertContains(
            'Modules\\Sale\\Contracts\\Events\\SaleOrderCompleted',
            $manifest['events']['publishes'],
        );
    }

    public function test_publishing_two_events_keeps_both_in_manifest(): void
    {
        $this->artisanPending('arch:make-integration-event', ['name' => 'Sale/SaleOrderCompleted'])->assertSuccessful();
        $this->artisanPending('arch:make-integration-event', ['name' => 'Sale/SaleOrderCancelled'])->assertSuccessful();

        $manifest = json_decode($this->files->get($this->modulesPath.'/Sale/module.json'), true);
        $this->assertEqualsCanonicalizing(
            [
                'Modules\\Sale\\Contracts\\Events\\SaleOrderCompleted',
                'Modules\\Sale\\Contracts\\Events\\SaleOrderCancelled',
            ],
            $manifest['events']['publishes'],
        );
    }
}
