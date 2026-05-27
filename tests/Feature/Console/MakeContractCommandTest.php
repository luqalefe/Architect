<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeContractCommandTest extends ArtifactCommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The publisher commands update module.json, so we need a real one.
        // Replace the bare directory created by the parent with a full module.
        $this->files->deleteDirectory($this->modulesPath.'/Sale');
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
    }

    public function test_creates_contract_interface_in_contracts_folder(): void
    {
        $this->artisanPending('arch:make-contract', ['name' => 'Sale/SaleOrderContract'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Contracts/SaleOrderContract.php');
    }

    public function test_generated_contract_is_an_interface_with_correct_namespace(): void
    {
        $this->artisanPending('arch:make-contract', ['name' => 'Sale/SaleOrderContract'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Contracts/SaleOrderContract.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Contracts;', $contents);
        $this->assertStringContainsString('interface SaleOrderContract', $contents);
    }

    public function test_module_json_lists_the_contract_in_publishes(): void
    {
        $this->artisanPending('arch:make-contract', ['name' => 'Sale/SaleOrderContract'])->assertSuccessful();

        $manifest = $this->reloadManifest();
        $this->assertContains(
            'Modules\\Sale\\Contracts\\SaleOrderContract',
            $manifest['contracts']['publishes'],
        );
    }

    public function test_re_running_with_force_does_not_duplicate_manifest_entry(): void
    {
        $this->artisanPending('arch:make-contract', ['name' => 'Sale/SaleOrderContract'])->assertSuccessful();
        $this->artisanPending('arch:make-contract', ['name' => 'Sale/SaleOrderContract', '--force' => true])->assertSuccessful();

        $manifest = $this->reloadManifest();
        $this->assertIsArray($manifest['contracts']);
        $this->assertIsArray($manifest['contracts']['publishes']);

        $matches = 0;
        foreach ($manifest['contracts']['publishes'] as $entry) {
            if ($entry === 'Modules\\Sale\\Contracts\\SaleOrderContract') {
                $matches++;
            }
        }
        $this->assertSame(1, $matches);
    }

    /**
     * @return array<string, mixed>
     */
    private function reloadManifest(): array
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($this->files->get($this->modulesPath.'/Sale/module.json'), true);

        return $manifest;
    }
}
