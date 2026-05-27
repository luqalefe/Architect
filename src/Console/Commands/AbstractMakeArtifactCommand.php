<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Console\Concerns\ParsesModuleAndName;
use LaravelModulesArch\Support\ModuleNaming;
use LaravelModulesArch\Support\StubRenderer;

/**
 * Shared scaffolding for the single-file DDD artifact generators
 * (arch:make-entity, arch:make-value-object, arch:make-enum,
 * arch:make-event, arch:make-exception).
 *
 * Subclasses declare three things and inherit all the validation, error
 * messaging, --force semantics and file writing.
 */
abstract class AbstractMakeArtifactCommand extends Command
{
    use ParsesModuleAndName;

    /**
     * Absolute path to the .stub file rendered by this command.
     */
    abstract protected function stubPath(): string;

    /**
     * Path under the module root (e.g. 'Domain/Entities/{{ NAME }}.php')
     * where the rendered file is written. May contain replacement tokens.
     */
    abstract protected function outputSubPath(): string;

    /**
     * Human-readable artifact label used in console messages (e.g. 'Entity').
     */
    abstract protected function artifactKind(): string;

    public function handle(Filesystem $files, StubRenderer $renderer): int
    {
        $argument = $this->argument('name');
        if (! is_string($argument) || $argument === '') {
            $this->components->error('Argument {name} is required and must be in Module/Name format.');

            return self::FAILURE;
        }

        $parsed = $this->parseModuleAndName($argument);
        if ($parsed === null) {
            return self::FAILURE;
        }
        [$module, $name] = $parsed;

        $modulesPath = (string) config('modules-arch.modules_path');
        $modulePath = $modulesPath.DIRECTORY_SEPARATOR.$module;

        if (! $files->isDirectory($modulePath)) {
            $this->components->error("Module {$module} not found at {$modulePath}. Run 'arch:make-module {$module}' first.");

            return self::FAILURE;
        }

        $replacements = ModuleNaming::replacements($module, $name);
        $outputPath = $modulePath.DIRECTORY_SEPARATOR.$renderer->render($this->outputSubPath(), $replacements);

        if ($files->exists($outputPath) && ! (bool) $this->option('force')) {
            $this->components->error(sprintf(
                '%s already exists at %s. Use --force to overwrite.',
                $this->artifactKind(),
                $outputPath,
            ));

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($outputPath));
        $files->put($outputPath, $renderer->renderFile($this->stubPath(), $replacements));

        $this->components->info(sprintf('%s created: %s', $this->artifactKind(), $outputPath));

        return self::SUCCESS;
    }

    protected function stubsRoot(): string
    {
        return dirname(__DIR__, 3).'/resources/stubs';
    }
}
