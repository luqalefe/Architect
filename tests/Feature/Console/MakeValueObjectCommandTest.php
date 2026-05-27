<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeValueObjectCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_value_object_at_expected_path(): void
    {
        $this->artisanPending('arch:make-value-object', ['name' => 'Sale/OrderTotal'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Domain/ValueObjects/OrderTotal.php');
    }

    public function test_generated_value_object_is_final_readonly_with_equals(): void
    {
        $this->artisanPending('arch:make-value-object', ['name' => 'Sale/OrderTotal'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Domain/ValueObjects/OrderTotal.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Domain\\ValueObjects;', $contents);
        $this->assertStringContainsString('final readonly class OrderTotal', $contents);
        $this->assertStringContainsString('public function equals(self $other): bool', $contents);
        $this->assertStringContainsString('use InvalidArgumentException;', $contents);
    }
}
