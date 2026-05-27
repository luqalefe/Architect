<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Tests\TestCase;

/**
 * Shared setup for the single-file artifact command tests.
 *
 * Creates a temp modules root and pre-creates a "Sale" module directory so
 * subclasses can immediately run `arch:make-*` against it.
 *
 * Filename does not end in `Test` so PHPUnit ignores it during discovery.
 */
abstract class ArtifactCommandTestCase extends TestCase
{
    protected string $modulesPath;

    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = sys_get_temp_dir().'/modules-arch-'.uniqid();
        $this->files->ensureDirectoryExists($this->modulesPath);
        $this->files->ensureDirectoryExists($this->modulesPath.'/Sale');

        config(['modules-arch.modules_path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        if ($this->files->exists($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }
}
