<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Analysis\Rules;

use LaravelModulesArch\Analysis\AnalysisContext;
use LaravelModulesArch\Analysis\ImportStatement;
use LaravelModulesArch\Analysis\ModuleFile;
use LaravelModulesArch\Analysis\Rule;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Rules\AggregateRootRepositoriesRule;
use LaravelModulesArch\Analysis\Rules\ApplicationCannotImportInfrastructureRule;
use LaravelModulesArch\Analysis\Rules\CrossModuleOnlyViaContractsRule;
use LaravelModulesArch\Analysis\Rules\DeclaredSubscriptionsRule;
use LaravelModulesArch\Analysis\Rules\DomainCannotImportApplicationRule;
use LaravelModulesArch\Analysis\Rules\DomainCannotImportInfrastructureRule;
use LaravelModulesArch\Analysis\Severity;
use LaravelModulesArch\Tests\TestCase;

class RulesTest extends TestCase
{
    // ---------- R1 ----------

    public function test_r1_passes_when_cross_module_import_goes_through_contracts(): void
    {
        $results = $this->runRule(
            new CrossModuleOnlyViaContractsRule,
            $this->makeFile('Sale', 'Application/Actions/CreateSaleOrder.php', 'Application', [
                'Modules\\Crm\\Contracts\\LeadContract',
            ]),
        );

        $this->assertSame([], $results);
    }

    public function test_r1_fails_when_cross_module_import_skips_contracts(): void
    {
        $results = $this->runRule(
            new CrossModuleOnlyViaContractsRule,
            $this->makeFile('Sale', 'Application/Actions/CreateSaleOrder.php', 'Application', [
                'Modules\\Crm\\Domain\\Entities\\Lead',
            ]),
        );

        $this->assertCount(1, $results);
        $this->assertSame(Severity::Error, $results[0]->severity);
        $this->assertSame('R1', $results[0]->rule);
    }

    public function test_r1_ignores_same_module_imports(): void
    {
        $results = $this->runRule(
            new CrossModuleOnlyViaContractsRule,
            $this->makeFile('Sale', 'Application/Actions/CreateSaleOrder.php', 'Application', [
                'Modules\\Sale\\Domain\\Entities\\SaleOrder',
            ]),
        );

        $this->assertSame([], $results);
    }

    // ---------- R2 ----------

    public function test_r2_passes_when_domain_imports_pure_php(): void
    {
        $results = $this->runRule(
            new DomainCannotImportInfrastructureRule,
            $this->makeFile('Sale', 'Domain/Entities/SaleOrder.php', 'Domain', [
                'DateTimeImmutable',
                'Modules\\Sale\\Domain\\ValueObjects\\OrderTotal',
            ]),
        );

        $this->assertSame([], $results);
    }

    public function test_r2_fails_when_domain_imports_own_infrastructure(): void
    {
        $results = $this->runRule(
            new DomainCannotImportInfrastructureRule,
            $this->makeFile('Sale', 'Domain/Entities/SaleOrder.php', 'Domain', [
                'Modules\\Sale\\Infrastructure\\Persistence\\Models\\SaleOrder',
            ]),
        );

        $this->assertCount(1, $results);
        $this->assertSame('R2', $results[0]->rule);
    }

    public function test_r2_fails_when_domain_imports_illuminate(): void
    {
        $results = $this->runRule(
            new DomainCannotImportInfrastructureRule,
            $this->makeFile('Sale', 'Domain/Entities/SaleOrder.php', 'Domain', [
                'Illuminate\\Database\\Eloquent\\Model',
            ]),
        );

        $this->assertCount(1, $results);
    }

    public function test_r2_allows_illuminate_namespaces_in_the_ignore_list(): void
    {
        $results = $this->runRule(
            new DomainCannotImportInfrastructureRule,
            $this->makeFile('Sale', 'Domain/Entities/SaleOrder.php', 'Domain', [
                'Illuminate\\Support\\Str',
            ]),
            ignored: ['Illuminate\\Support\\'],
        );

        $this->assertSame([], $results);
    }

    public function test_r2_skips_non_domain_files(): void
    {
        $results = $this->runRule(
            new DomainCannotImportInfrastructureRule,
            $this->makeFile('Sale', 'Application/Actions/CreateSaleOrder.php', 'Application', [
                'Modules\\Sale\\Infrastructure\\Persistence\\Models\\SaleOrder',
            ]),
        );

        $this->assertSame([], $results);
    }

    // ---------- R3 ----------

    public function test_r3_fails_when_domain_imports_application(): void
    {
        $results = $this->runRule(
            new DomainCannotImportApplicationRule,
            $this->makeFile('Sale', 'Domain/Entities/SaleOrder.php', 'Domain', [
                'Modules\\Sale\\Application\\Actions\\CreateSaleOrder',
            ]),
        );

        $this->assertCount(1, $results);
        $this->assertSame('R3', $results[0]->rule);
    }

    public function test_r3_passes_when_domain_does_not_touch_application(): void
    {
        $results = $this->runRule(
            new DomainCannotImportApplicationRule,
            $this->makeFile('Sale', 'Domain/Entities/SaleOrder.php', 'Domain', [
                'Modules\\Sale\\Domain\\ValueObjects\\OrderTotal',
            ]),
        );

        $this->assertSame([], $results);
    }

    // ---------- R4 ----------

    public function test_r4_fails_when_application_imports_infrastructure(): void
    {
        $results = $this->runRule(
            new ApplicationCannotImportInfrastructureRule,
            $this->makeFile('Sale', 'Application/Actions/CreateSaleOrder.php', 'Application', [
                'Modules\\Sale\\Infrastructure\\Persistence\\Models\\SaleOrder',
            ]),
        );

        $this->assertCount(1, $results);
        $this->assertSame('R4', $results[0]->rule);
    }

    public function test_r4_passes_when_application_only_touches_domain(): void
    {
        $results = $this->runRule(
            new ApplicationCannotImportInfrastructureRule,
            $this->makeFile('Sale', 'Application/Actions/CreateSaleOrder.php', 'Application', [
                'Modules\\Sale\\Domain\\Entities\\SaleOrder',
                'Modules\\Sale\\Domain\\Repositories\\SaleOrderRepositoryInterface',
            ]),
        );

        $this->assertSame([], $results);
    }

    // ---------- R5 ----------

    public function test_r5_passes_when_subscribed_event_is_declared(): void
    {
        $context = new AnalysisContext(
            manifests: ['Sale' => ['events' => ['subscribes' => ['Modules\\Crm\\Contracts\\Events\\LeadConverted']]]],
            ignoredNamespaces: [],
        );

        $file = $this->makeFile('Sale', 'Infrastructure/ACL/HandleLeadConverted.php', 'Infrastructure', [
            'Modules\\Crm\\Contracts\\Events\\LeadConverted',
        ]);

        $this->assertSame([], (new DeclaredSubscriptionsRule)->evaluate($file, $context));
    }

    public function test_r5_fails_when_subscribed_event_is_missing_from_manifest(): void
    {
        $context = new AnalysisContext(
            manifests: ['Sale' => ['events' => ['subscribes' => []]]],
            ignoredNamespaces: [],
        );

        $file = $this->makeFile('Sale', 'Infrastructure/ACL/HandleLeadConverted.php', 'Infrastructure', [
            'Modules\\Crm\\Contracts\\Events\\LeadConverted',
        ]);

        $results = (new DeclaredSubscriptionsRule)->evaluate($file, $context);

        $this->assertCount(1, $results);
        $this->assertSame('R5', $results[0]->rule);
    }

    public function test_r5_only_scans_acl_listeners(): void
    {
        $context = new AnalysisContext(manifests: ['Sale' => []], ignoredNamespaces: []);

        $file = $this->makeFile('Sale', 'Application/Actions/CreateSaleOrder.php', 'Application', [
            'Modules\\Crm\\Contracts\\Events\\LeadConverted',
        ]);

        $this->assertSame([], (new DeclaredSubscriptionsRule)->evaluate($file, $context));
    }

    // ---------- R6 ----------

    public function test_r6_warns_for_child_entity_suffix(): void
    {
        $file = $this->makeFile('Sale', 'Domain/Repositories/SaleOrderItemRepositoryInterface.php', 'Domain', []);

        $results = (new AggregateRootRepositoriesRule)->evaluate($file, $this->emptyContext());

        $this->assertCount(1, $results);
        $this->assertSame(Severity::Warning, $results[0]->severity);
        $this->assertSame('R6', $results[0]->rule);
    }

    public function test_r6_silent_for_plain_aggregate_root(): void
    {
        $file = $this->makeFile('Sale', 'Domain/Repositories/SaleOrderRepositoryInterface.php', 'Domain', []);

        $this->assertSame([], (new AggregateRootRepositoriesRule)->evaluate($file, $this->emptyContext()));
    }

    public function test_r6_only_scans_repository_interfaces(): void
    {
        $file = $this->makeFile('Sale', 'Domain/Entities/SaleOrderItem.php', 'Domain', []);

        $this->assertSame([], (new AggregateRootRepositoriesRule)->evaluate($file, $this->emptyContext()));
    }

    // ---------- helpers ----------

    /**
     * @param  list<string>  $ignored
     * @return list<RuleResult>
     */
    private function runRule(Rule $rule, ModuleFile $file, array $ignored = []): array
    {
        return $rule->evaluate($file, new AnalysisContext(manifests: [], ignoredNamespaces: $ignored));
    }

    /**
     * @param  list<string>  $importNamespaces
     */
    private function makeFile(string $module, string $relativePath, string $layer, array $importNamespaces): ModuleFile
    {
        $imports = array_map(
            fn (string $ns, int $i) => new ImportStatement($ns, $i + 5),
            $importNamespaces,
            array_keys($importNamespaces),
        );

        return new ModuleFile(
            path: '/tmp/Modules/'.$module.'/'.$relativePath,
            module: $module,
            relativePath: $relativePath,
            layer: $layer,
            imports: $imports,
        );
    }

    private function emptyContext(): AnalysisContext
    {
        return new AnalysisContext(manifests: [], ignoredNamespaces: []);
    }
}
