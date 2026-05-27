<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature;

use LaravelModulesArch\Tests\TestCase;

/**
 * Locks in the promise made by the example/ directory: the reference Sale +
 * Crm modules pass `arch:check-boundaries --strict` against the same rule
 * set the package recommends by default.
 *
 * If this test fails, the example is teaching something the enforcement
 * doesn't allow — either fix the example or relax the rule.
 */
class ExampleModuleConformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['modules-arch.modules_path' => dirname(__DIR__, 2).'/examples/Modules']);
        config(['modules-arch.boundaries.enabled' => true]);
        config(['modules-arch.boundaries.rules' => [
            'cross_module_via_contracts' => true,
            'domain_no_infrastructure' => true,
            'domain_no_application' => true,
            'application_no_infrastructure' => false,
            'declared_subscriptions' => true,
            'aggregate_root_repositories' => true,
        ]]);
        config(['modules-arch.boundaries.ignored_namespaces' => [
            'Illuminate\\Support\\',
            'Illuminate\\Contracts\\',
        ]]);
    }

    public function test_examples_pass_arch_check_boundaries_in_strict_mode(): void
    {
        $this->artisanPending('arch:check-boundaries', ['--strict' => true])
            ->expectsOutputToContain('No boundary violations found.')
            ->assertExitCode(0);
    }
}
