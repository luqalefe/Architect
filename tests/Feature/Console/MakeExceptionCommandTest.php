<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

class MakeExceptionCommandTest extends ArtifactCommandTestCase
{
    public function test_creates_exception_at_expected_path(): void
    {
        $this->artisanPending('arch:make-exception', ['name' => 'Sale/InvalidOrderTransitionException'])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Domain/Exceptions/InvalidOrderTransitionException.php');
    }

    public function test_generated_exception_extends_domain_exception_with_named_constructor(): void
    {
        $this->artisanPending('arch:make-exception', ['name' => 'Sale/InvalidOrderTransitionException'])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Domain/Exceptions/InvalidOrderTransitionException.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Domain\\Exceptions;', $contents);
        $this->assertStringContainsString('final class InvalidOrderTransitionException extends DomainException', $contents);
        $this->assertStringContainsString('public static function because(string $reason): self', $contents);
    }
}
