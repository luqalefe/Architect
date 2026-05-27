<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Analysis;

use LaravelModulesArch\Analysis\RuleRegistry;
use LaravelModulesArch\Analysis\Rules\AggregateRootRepositoriesRule;
use LaravelModulesArch\Analysis\Rules\ApplicationCannotImportInfrastructureRule;
use LaravelModulesArch\Analysis\Rules\CrossModuleOnlyViaContractsRule;
use LaravelModulesArch\Tests\TestCase;

class RuleRegistryTest extends TestCase
{
    public function test_returns_only_enabled_rules(): void
    {
        $rules = (new RuleRegistry)->enabled([
            'cross_module_via_contracts' => true,
            'domain_no_infrastructure' => false,
            'domain_no_application' => false,
            'application_no_infrastructure' => false,
            'declared_subscriptions' => false,
            'aggregate_root_repositories' => true,
        ]);

        $codes = array_map(fn ($r) => $r->code(), $rules);

        $this->assertCount(2, $rules);
        $this->assertContains('R1', $codes);
        $this->assertContains('R6', $codes);
    }

    public function test_pragmatic_default_config_excludes_r4(): void
    {
        $rules = (new RuleRegistry)->enabled([
            'cross_module_via_contracts' => true,
            'domain_no_infrastructure' => true,
            'domain_no_application' => true,
            'application_no_infrastructure' => false,
            'declared_subscriptions' => true,
            'aggregate_root_repositories' => true,
        ]);

        $codes = array_map(fn ($r) => $r->code(), $rules);

        $this->assertCount(5, $rules);
        $this->assertNotContains('R4', $codes);
    }

    public function test_class_instances_correspond_to_keys(): void
    {
        $rules = (new RuleRegistry)->enabled([
            'cross_module_via_contracts' => true,
            'application_no_infrastructure' => true,
            'aggregate_root_repositories' => true,
        ]);

        $classes = array_map(get_class(...), $rules);

        $this->assertContains(CrossModuleOnlyViaContractsRule::class, $classes);
        $this->assertContains(ApplicationCannotImportInfrastructureRule::class, $classes);
        $this->assertContains(AggregateRootRepositoriesRule::class, $classes);
    }

    public function test_empty_config_yields_no_rules(): void
    {
        $this->assertSame([], (new RuleRegistry)->enabled([]));
    }

    public function test_resolve_for_mode_forces_r4_on_in_purist(): void
    {
        $resolved = RuleRegistry::resolveForMode([
            'cross_module_via_contracts' => true,
            'application_no_infrastructure' => false,
        ], 'purist');

        $this->assertTrue($resolved['application_no_infrastructure']);
        $this->assertTrue($resolved['cross_module_via_contracts']);
    }

    public function test_resolve_for_mode_leaves_config_alone_in_pragmatic(): void
    {
        $input = [
            'cross_module_via_contracts' => true,
            'application_no_infrastructure' => false,
        ];

        $this->assertSame($input, RuleRegistry::resolveForMode($input, 'pragmatic'));
    }

    public function test_purist_resolution_actually_enables_r4(): void
    {
        $rules = (new RuleRegistry)->enabled(RuleRegistry::resolveForMode([
            'cross_module_via_contracts' => true,
            'application_no_infrastructure' => false,
        ], 'purist'));

        $this->assertContains('R4', array_map(fn ($r) => $r->code(), $rules));
    }
}
