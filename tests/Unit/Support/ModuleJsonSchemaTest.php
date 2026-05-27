<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Support;

use LaravelModulesArch\Support\ModuleJsonSchema;
use LaravelModulesArch\Tests\TestCase;

class ModuleJsonSchemaTest extends TestCase
{
    public function test_accepts_a_minimal_valid_manifest(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'Sale',
            'providers' => ['Modules\\Sale\\Infrastructure\\Providers\\SaleServiceProvider'],
        ]);

        $this->assertTrue($result->isValid(), $result->summary());
        $this->assertSame([], $result->errors());
    }

    public function test_accepts_the_full_manifest_shape_from_the_design_spec(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'Sale',
            'alias' => 'sale',
            'description' => 'Sales bounded context.',
            'priority' => 0,
            'version' => '1.0.0',
            'providers' => ['Modules\\Sale\\Infrastructure\\Providers\\SaleServiceProvider'],
            'contracts' => [
                'publishes' => [
                    'Modules\\Sale\\Contracts\\SaleOrderContract',
                ],
            ],
            'events' => [
                'publishes' => ['Modules\\Sale\\Contracts\\Events\\SaleOrderCompleted'],
                'subscribes' => ['Modules\\Crm\\Contracts\\Events\\LeadConverted'],
            ],
            'dependencies' => [
                'required' => ['Base'],
                'optional' => ['Crm', 'Stock'],
            ],
        ]);

        $this->assertTrue($result->isValid(), $result->summary());
    }

    public function test_rejects_manifest_missing_required_name(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'providers' => ['SomeProvider'],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors());
        $this->assertStringContainsString('name', strtolower($result->summary()));
    }

    public function test_rejects_manifest_with_empty_providers_list(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'Sale',
            'providers' => [],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('providers', strtolower($result->summary()));
    }

    public function test_rejects_name_that_is_not_studly_case(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'sale',
            'providers' => ['SomeProvider'],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('name', strtolower($result->summary()));
    }

    public function test_rejects_alias_that_is_not_snake_case(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'Sale',
            'alias' => 'SaleAlias',
            'providers' => ['SomeProvider'],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('alias', strtolower($result->summary()));
    }

    public function test_rejects_priority_with_wrong_type(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'Sale',
            'priority' => 'high',
            'providers' => ['SomeProvider'],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('priority', strtolower($result->summary()));
    }

    public function test_rejects_unknown_keys_inside_contracts_block(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'Sale',
            'providers' => ['SomeProvider'],
            'contracts' => [
                'publishes' => [],
                'consumes' => [],
            ],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('consumes', $result->summary());
    }

    public function test_allows_unknown_top_level_keys_for_nwidart_compatibility(): void
    {
        $result = (new ModuleJsonSchema)->validate([
            'name' => 'Sale',
            'providers' => ['SomeProvider'],
            'files' => ['start.php'],
            'keywords' => ['sales', 'orders'],
        ]);

        $this->assertTrue($result->isValid(), $result->summary());
    }
}
