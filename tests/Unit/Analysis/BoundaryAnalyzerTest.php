<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Analysis;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Analysis\BoundaryAnalyzer;
use LaravelModulesArch\Analysis\ImportExtractor;
use LaravelModulesArch\Analysis\ModuleScanner;
use LaravelModulesArch\Analysis\RuleRegistry;
use LaravelModulesArch\Tests\TestCase;

class BoundaryAnalyzerTest extends TestCase
{
    private string $modulesPath;

    private Filesystem $files;

    private BoundaryAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = sys_get_temp_dir().'/boundary-analyzer-'.uniqid();
        $this->files->ensureDirectoryExists($this->modulesPath);

        $this->analyzer = new BoundaryAnalyzer($this->files, new ModuleScanner($this->files, new ImportExtractor));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_clean_modules_produce_no_violations(): void
    {
        $this->writeManifest('Sale');
        $this->writeFile('Sale/Domain/Entities/SaleOrder.php', <<<'PHP'
            <?php
            namespace Modules\Sale\Domain\Entities;
            final class SaleOrder {}
            PHP);

        $rules = (new RuleRegistry)->enabled($this->pragmaticRules());

        $results = $this->analyzer->analyze($this->modulesPath, $rules);

        $this->assertSame([], $results);
    }

    public function test_detects_cross_module_violation_via_r1(): void
    {
        $this->writeManifest('Sale');
        $this->writeManifest('Crm');
        $this->writeFile('Sale/Application/Actions/CreateSaleOrder.php', <<<'PHP'
            <?php
            namespace Modules\Sale\Application\Actions;
            use Modules\Crm\Domain\Entities\Lead;
            final class CreateSaleOrder {}
            PHP);

        $rules = (new RuleRegistry)->enabled($this->pragmaticRules());
        $results = $this->analyzer->analyze($this->modulesPath, $rules);

        $this->assertCount(1, $results);
        $this->assertSame('R1', $results[0]->rule);
    }

    public function test_only_module_filter_scopes_violations(): void
    {
        $this->writeManifest('Sale');
        $this->writeManifest('Crm');
        $this->writeFile('Sale/Application/Actions/Foo.php', <<<'PHP'
            <?php
            namespace Modules\Sale\Application\Actions;
            use Modules\Crm\Domain\Entities\Lead;
            class Foo {}
            PHP);
        $this->writeFile('Crm/Application/Actions/Bar.php', <<<'PHP'
            <?php
            namespace Modules\Crm\Application\Actions;
            use Modules\Sale\Domain\Entities\Order;
            class Bar {}
            PHP);

        $rules = (new RuleRegistry)->enabled($this->pragmaticRules());

        $saleOnly = $this->analyzer->analyze($this->modulesPath, $rules, onlyModule: 'Sale');
        $this->assertCount(1, $saleOnly);
        $this->assertStringContainsString('Sale', $saleOnly[0]->file);
    }

    public function test_ignored_namespaces_let_domain_use_illuminate_support(): void
    {
        $this->writeManifest('Sale');
        $this->writeFile('Sale/Domain/ValueObjects/OrderTotal.php', <<<'PHP'
            <?php
            namespace Modules\Sale\Domain\ValueObjects;
            use Illuminate\Support\Str;
            final readonly class OrderTotal {}
            PHP);

        $rules = (new RuleRegistry)->enabled($this->pragmaticRules());

        $without = $this->analyzer->analyze($this->modulesPath, $rules);
        $with = $this->analyzer->analyze($this->modulesPath, $rules, ['Illuminate\\Support\\']);

        $this->assertCount(1, $without);
        $this->assertSame([], $with);
    }

    public function test_disabled_rules_do_not_run(): void
    {
        $this->writeManifest('Sale');
        $this->writeFile('Sale/Domain/Entities/SaleOrder.php', <<<'PHP'
            <?php
            namespace Modules\Sale\Domain\Entities;
            use Modules\Sale\Infrastructure\Persistence\Models\SaleOrder as Model;
            final class SaleOrder {}
            PHP);

        $rulesWithR2 = (new RuleRegistry)->enabled(['domain_no_infrastructure' => true]);
        $rulesWithoutR2 = (new RuleRegistry)->enabled([]);

        $this->assertCount(1, $this->analyzer->analyze($this->modulesPath, $rulesWithR2));
        $this->assertSame([], $this->analyzer->analyze($this->modulesPath, $rulesWithoutR2));
    }

    public function test_loads_manifests_for_r5_evaluation(): void
    {
        $this->writeManifest('Sale', ['events' => ['subscribes' => []]]);
        $this->writeManifest('Crm');
        $this->writeFile('Sale/Infrastructure/ACL/HandleLeadConverted.php', <<<'PHP'
            <?php
            namespace Modules\Sale\Infrastructure\ACL;
            use Modules\Crm\Contracts\Events\LeadConverted;
            final class HandleLeadConverted {}
            PHP);

        $rules = (new RuleRegistry)->enabled(['declared_subscriptions' => true]);
        $results = $this->analyzer->analyze($this->modulesPath, $rules);

        $this->assertCount(1, $results);
        $this->assertSame('R5', $results[0]->rule);
    }

    /**
     * @return array<string, bool>
     */
    private function pragmaticRules(): array
    {
        return [
            'cross_module_via_contracts' => true,
            'domain_no_infrastructure' => true,
            'domain_no_application' => true,
            'application_no_infrastructure' => false,
            'declared_subscriptions' => true,
            'aggregate_root_repositories' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function writeManifest(string $module, array $overrides = []): void
    {
        $manifest = array_replace_recursive([
            'name' => $module,
            'alias' => strtolower($module),
            'providers' => ['Modules\\'.$module.'\\Infrastructure\\Providers\\'.$module.'ServiceProvider'],
            'contracts' => ['publishes' => []],
            'events' => ['publishes' => [], 'subscribes' => []],
        ], $overrides);

        $this->files->ensureDirectoryExists($this->modulesPath.'/'.$module);
        $this->files->put(
            $this->modulesPath.'/'.$module.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function writeFile(string $relative, string $contents): void
    {
        $path = $this->modulesPath.'/'.$relative;
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
    }
}
