<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit;

use LaravelModulesArch\Tests\TestCase;

class SanityTest extends TestCase
{
    public function test_package_config_is_loaded(): void
    {
        $this->assertIsArray(config('modules-arch'));
        $this->assertArrayHasKey('default_mode', config('modules-arch'));
        $this->assertArrayHasKey('modules_path', config('modules-arch'));
        $this->assertArrayHasKey('boundaries', config('modules-arch'));
    }

    public function test_default_mode_is_pragmatic(): void
    {
        $this->assertSame('pragmatic', config('modules-arch.default_mode'));
    }

    public function test_default_rules_match_pragmatic_mode(): void
    {
        $rules = config('modules-arch.boundaries.rules');

        $this->assertTrue($rules['cross_module_via_contracts']);
        $this->assertTrue($rules['domain_no_infrastructure']);
        $this->assertTrue($rules['domain_no_application']);
        $this->assertFalse($rules['application_no_infrastructure'], 'R4 must default to OFF in pragmatic mode.');
        $this->assertTrue($rules['declared_subscriptions']);
        $this->assertTrue($rules['aggregate_root_repositories']);
    }
}
