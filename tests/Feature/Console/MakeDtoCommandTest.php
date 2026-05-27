<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeDtoCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_dto_at_expected_path(): void
    {
        $this->artisanPending('arch:make-dto', ['name' => 'Sale/CreateSaleOrderData'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Application/DTOs/CreateSaleOrderData.php');
    }

    public function test_generated_dto_is_final_readonly_with_from_request_and_from_array(): void
    {
        $this->artisanPending('arch:make-dto', ['name' => 'Sale/CreateSaleOrderData'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Application/DTOs/CreateSaleOrderData.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Application\\DTOs;', $contents);
        $this->assertStringContainsString('use Illuminate\\Http\\Request;', $contents);
        $this->assertStringContainsString('final readonly class CreateSaleOrderData', $contents);
        $this->assertStringContainsString('public static function fromRequest(Request $request): self', $contents);
        $this->assertStringContainsString('public static function fromArray(array $data): self', $contents);
    }
}
