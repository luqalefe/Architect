<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LaravelModulesArch\Support\ModuleJsonSchema;
use LaravelModulesArch\Support\StubRenderer;

class MakeModuleCommand extends Command
{
    protected $signature = 'arch:make-module
                            {name : StudlyCase module name, e.g. Sale}
                            {--mode= : pragmatic|purist (defaults to config modules-arch.default_mode)}
                            {--force : Overwrite an existing module}';

    protected $description = 'Scaffold a new DDD Bounded Context with the full folder layout.';

    /**
     * Stub-to-output map. Both paths and stub contents are run through the
     * renderer, so {{ NAME }} works in either side.
     *
     * @var array<string, string>
     */
    private const STUB_MAP = [
        'module.json.stub' => 'module.json',
        'composer.json.stub' => 'composer.json',
        'Infrastructure/Providers/ModuleServiceProvider.stub' => 'Infrastructure/Providers/{{ NAME }}ServiceProvider.php',
        'routes/web.php.stub' => 'routes/web.php',
        'routes/api.php.stub' => 'routes/api.php',
    ];

    /**
     * Directories that are intentionally empty and get a .gitkeep so git
     * tracks them. Layout for both modes; purist adds extras separately.
     *
     * @var list<string>
     */
    private const BASE_EMPTY_DIRECTORIES = [
        'Contracts',
        'Domain/Enums',
        'Domain/Events',
        'Domain/Exceptions',
        'Application/Actions',
        'Application/DTOs',
        'Application/ViewModels',
        'Application/Validators',
        'Infrastructure/Http/Controllers',
        'Infrastructure/Http/Requests',
        'Infrastructure/Http/Resources',
        'Infrastructure/Persistence/Models',
        'Infrastructure/ACL',
        'database/migrations',
        'database/factories',
        'database/seeders',
        'resources/views',
        'tests/Unit/Domain',
        'tests/Feature/Application',
    ];

    /**
     * Extra directories only generated in purist mode (pure entities + repos).
     *
     * @var list<string>
     */
    private const PURIST_EXTRA_DIRECTORIES = [
        'Domain/Entities',
        'Domain/ValueObjects',
        'Domain/Repositories',
        'Infrastructure/Persistence/Repositories',
    ];

    public function handle(Filesystem $files, StubRenderer $renderer, ModuleJsonSchema $schema): int
    {
        $name = $this->argument('name');
        if (! is_string($name) || preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            $given = is_string($name) ? "'{$name}'" : gettype($name);
            $this->components->error("Module name must be StudlyCase (e.g. Sale, CustomerSupport). Got: {$given}.");

            return self::FAILURE;
        }

        $modeOption = $this->option('mode');
        $mode = is_string($modeOption) && $modeOption !== ''
            ? $modeOption
            : (string) config('modules-arch.default_mode', 'pragmatic');
        if (! in_array($mode, ['pragmatic', 'purist'], true)) {
            $this->components->error("--mode must be 'pragmatic' or 'purist'. Got: '{$mode}'.");

            return self::FAILURE;
        }

        $modulesPath = (string) config('modules-arch.modules_path');
        $modulePath = $modulesPath.DIRECTORY_SEPARATOR.$name;

        if ($files->exists($modulePath) && ! (bool) $this->option('force')) {
            $this->components->error("Module already exists at {$modulePath}. Use --force to overwrite.");

            return self::FAILURE;
        }

        $replacements = $this->buildReplacements($name);

        $this->createDirectories($files, $modulePath, $mode);
        $this->renderStubs($files, $renderer, $modulePath, $replacements);

        $validationResult = $this->validateGeneratedManifest($files, $schema, $modulePath);
        if ($validationResult !== self::SUCCESS) {
            return $validationResult;
        }

        $this->components->info("Module {$name} created at {$modulePath} ({$mode} mode).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function buildReplacements(string $name): array
    {
        $alias = Str::snake($name);
        $namespace = 'Modules\\'.$name;

        return [
            'NAME' => $name,
            'ALIAS' => $alias,
            'NAMESPACE' => $namespace,
            // JSON requires backslashes to be escaped, so we double them up
            // for any token rendered inside JSON content.
            'NAMESPACE_JSON' => str_replace('\\', '\\\\', $namespace),
            'DESCRIPTION' => $name.' bounded context.',
        ];
    }

    private function createDirectories(Filesystem $files, string $modulePath, string $mode): void
    {
        $directories = self::BASE_EMPTY_DIRECTORIES;
        if ($mode === 'purist') {
            $directories = array_merge($directories, self::PURIST_EXTRA_DIRECTORIES);
        }

        foreach ($directories as $directory) {
            $path = $modulePath.DIRECTORY_SEPARATOR.$directory;
            $files->ensureDirectoryExists($path);
            $files->put($path.DIRECTORY_SEPARATOR.'.gitkeep', '');
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function renderStubs(
        Filesystem $files,
        StubRenderer $renderer,
        string $modulePath,
        array $replacements,
    ): void {
        $stubRoot = dirname(__DIR__, 3).'/resources/stubs/module';

        foreach (self::STUB_MAP as $stub => $outputTemplate) {
            $stubPath = $stubRoot.'/'.$stub;
            $outputPath = $modulePath.DIRECTORY_SEPARATOR.$renderer->render($outputTemplate, $replacements);

            $files->ensureDirectoryExists(dirname($outputPath));
            $files->put($outputPath, $renderer->renderFile($stubPath, $replacements));
        }
    }

    private function validateGeneratedManifest(Filesystem $files, ModuleJsonSchema $schema, string $modulePath): int
    {
        $manifestPath = $modulePath.'/module.json';
        $raw = $files->get($manifestPath);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $this->components->error('Generated module.json is not valid JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        /** @var array<string, mixed> $decoded */
        $result = $schema->validate($decoded);
        if (! $result->isValid()) {
            $this->components->error("Generated module.json failed schema validation:\n".$result->summary());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
