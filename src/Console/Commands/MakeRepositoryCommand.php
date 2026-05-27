<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Console\Concerns\ParsesModuleAndName;
use LaravelModulesArch\Support\ModuleNaming;
use LaravelModulesArch\Support\StubRenderer;

/**
 * Generates a Repository pair (Domain interface + Eloquent implementation)
 * and auto-wires the binding inside the module's ServiceProvider between the
 * `// @arch-bindings-start` / `// @arch-bindings-end` markers.
 *
 * The wiring step is idempotent: if a binding for the same interface FQCN is
 * already in the file, it's skipped with a warning so re-running with
 * `--force` doesn't duplicate.
 */
class MakeRepositoryCommand extends Command
{
    use ParsesModuleAndName;

    protected $signature = 'arch:make-repository
                            {name : Module/EntityName, e.g. Sale/SaleOrder}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate a Repository interface + Eloquent implementation, auto-wired in the ServiceProvider.';

    private const BINDINGS_END_MARKER = '// @arch-bindings-end';

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
        $force = (bool) $this->option('force');

        $interfacePath = $modulePath.'/Domain/Repositories/'.$name.'RepositoryInterface.php';
        $implPath = $modulePath.'/Infrastructure/Persistence/Repositories/Eloquent'.$name.'Repository.php';

        foreach ([$interfacePath, $implPath] as $path) {
            if ($files->exists($path) && ! $force) {
                $this->components->error("File already exists: {$path}. Use --force to overwrite.");

                return self::FAILURE;
            }
        }

        $stubs = dirname(__DIR__, 3).'/resources/stubs';

        $files->ensureDirectoryExists(dirname($interfacePath));
        $files->put($interfacePath, $renderer->renderFile($stubs.'/domain/RepositoryInterface.stub', $replacements));

        $files->ensureDirectoryExists(dirname($implPath));
        $files->put($implPath, $renderer->renderFile($stubs.'/infrastructure/EloquentRepository.stub', $replacements));

        $this->components->info("Repository created: {$interfacePath}");
        $this->components->info("Repository created: {$implPath}");

        $this->wireBinding($files, $modulePath, $module, $name);

        return self::SUCCESS;
    }

    private function wireBinding(Filesystem $files, string $modulePath, string $module, string $name): void
    {
        $spPath = $modulePath.'/Infrastructure/Providers/'.$module.'ServiceProvider.php';

        if (! $files->exists($spPath)) {
            $this->components->warn("ServiceProvider not found at {$spPath} — binding not auto-wired.");

            return;
        }

        $contents = $files->get($spPath);

        $interfaceFqcn = 'Modules\\'.$module.'\\Domain\\Repositories\\'.$name.'RepositoryInterface';
        $implFqcn = 'Modules\\'.$module.'\\Infrastructure\\Persistence\\Repositories\\Eloquent'.$name.'Repository';

        if (str_contains($contents, $interfaceFqcn.'::class')) {
            $this->components->warn("Binding for {$interfaceFqcn} already present in {$module}ServiceProvider — skipping.");

            return;
        }

        if (! str_contains($contents, self::BINDINGS_END_MARKER)) {
            $this->components->warn(self::BINDINGS_END_MARKER." marker not found in {$module}ServiceProvider — add binding manually.");

            return;
        }

        $indent = '        ';
        $insertion = $indent.'$this->app->bind('."\n"
            .$indent.'    \\'.$interfaceFqcn.'::class,'."\n"
            .$indent.'    \\'.$implFqcn.'::class,'."\n"
            .$indent.');'."\n";

        $contents = str_replace(
            $indent.self::BINDINGS_END_MARKER,
            $insertion.$indent.self::BINDINGS_END_MARKER,
            $contents,
        );

        $files->put($spPath, $contents);
        $this->components->info("Binding wired in {$module}ServiceProvider.");
    }
}
