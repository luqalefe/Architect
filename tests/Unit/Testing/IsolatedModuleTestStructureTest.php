<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Testing;

use LaravelModulesArch\Testing\IsolatedModuleTest;
use LaravelModulesArch\Tests\TestCase;
use ReflectionClass;

class IsolatedModuleTestStructureTest extends TestCase
{
    public function test_class_is_abstract(): void
    {
        $this->assertTrue((new ReflectionClass(IsolatedModuleTest::class))->isAbstract());
    }

    public function test_declares_enabled_modules_property_with_empty_default(): void
    {
        $reflection = new ReflectionClass(IsolatedModuleTest::class);

        $this->assertTrue($reflection->hasProperty('enabledModules'));

        $property = $reflection->getProperty('enabledModules');
        $this->assertTrue($property->isProtected());
        $this->assertSame([], $property->getDefaultValue());
    }
}
