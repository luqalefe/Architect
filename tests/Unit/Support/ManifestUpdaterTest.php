<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Support\ManifestUpdater;
use LaravelModulesArch\Support\ModuleJsonSchema;
use LaravelModulesArch\Tests\TestCase;
use RuntimeException;

class ManifestUpdaterTest extends TestCase
{
    private string $manifestPath;

    private Filesystem $files;

    private ManifestUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->manifestPath = sys_get_temp_dir().'/manifest-updater-'.uniqid().'.json';
        $this->updater = new ManifestUpdater($this->files, new ModuleJsonSchema);

        $this->files->put($this->manifestPath, json_encode([
            'name' => 'Sale',
            'alias' => 'sale',
            'providers' => ['Modules\\Sale\\Infrastructure\\Providers\\SaleServiceProvider'],
            'contracts' => ['publishes' => []],
            'events' => ['publishes' => [], 'subscribes' => []],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        @unlink($this->manifestPath);
        parent::tearDown();
    }

    public function test_adds_entry_to_existing_empty_list(): void
    {
        $result = $this->updater->addToList(
            $this->manifestPath,
            'contracts.publishes',
            'Modules\\Sale\\Contracts\\SaleOrderContract',
        );

        $this->assertTrue($result->wasAdded());
        $this->assertTrue($result->isValid(), $result->validation->summary());

        $manifest = $this->reload();
        $this->assertSame(
            ['Modules\\Sale\\Contracts\\SaleOrderContract'],
            $manifest['contracts']['publishes'],
        );
    }

    public function test_skips_duplicate_entries(): void
    {
        $entry = 'Modules\\Sale\\Contracts\\SaleOrderContract';

        $this->updater->addToList($this->manifestPath, 'contracts.publishes', $entry);
        $second = $this->updater->addToList($this->manifestPath, 'contracts.publishes', $entry);

        $this->assertFalse($second->wasAdded(), 'Second add for the same entry must skip.');

        $manifest = $this->reload();
        $this->assertCount(1, $manifest['contracts']['publishes']);
    }

    public function test_appends_to_existing_populated_list(): void
    {
        $this->updater->addToList($this->manifestPath, 'events.publishes', 'Modules\\Sale\\Contracts\\Events\\SaleOrderCompleted');
        $this->updater->addToList($this->manifestPath, 'events.publishes', 'Modules\\Sale\\Contracts\\Events\\SaleOrderCancelled');

        $manifest = $this->reload();
        $this->assertSame(
            [
                'Modules\\Sale\\Contracts\\Events\\SaleOrderCompleted',
                'Modules\\Sale\\Contracts\\Events\\SaleOrderCancelled',
            ],
            $manifest['events']['publishes'],
        );
    }

    public function test_writes_subscribes_independently_of_publishes(): void
    {
        $this->updater->addToList($this->manifestPath, 'events.publishes', 'Modules\\Sale\\Contracts\\Events\\Foo');
        $this->updater->addToList($this->manifestPath, 'events.subscribes', 'Modules\\Crm\\Contracts\\Events\\LeadConverted');

        $manifest = $this->reload();
        $this->assertSame(['Modules\\Sale\\Contracts\\Events\\Foo'], $manifest['events']['publishes']);
        $this->assertSame(['Modules\\Crm\\Contracts\\Events\\LeadConverted'], $manifest['events']['subscribes']);
    }

    public function test_writes_manifest_pretty_printed_with_trailing_newline(): void
    {
        $this->updater->addToList($this->manifestPath, 'contracts.publishes', 'Modules\\Sale\\Contracts\\Foo');

        $raw = $this->files->get($this->manifestPath);
        $this->assertStringEndsWith("\n", $raw);
        $this->assertStringContainsString("    \"contracts\": {\n", $raw, 'Manifest should be pretty-printed.');
    }

    public function test_throws_when_manifest_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('module.json not found');

        $this->updater->addToList('/no/such/path/module.json', 'contracts.publishes', 'X');
    }

    public function test_throws_when_manifest_is_invalid_json(): void
    {
        $this->files->put($this->manifestPath, '{not json');

        $this->expectException(RuntimeException::class);

        $this->updater->addToList($this->manifestPath, 'contracts.publishes', 'X');
    }

    /**
     * @return array<string, mixed>
     */
    private function reload(): array
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($this->files->get($this->manifestPath), true);

        return $manifest;
    }
}
