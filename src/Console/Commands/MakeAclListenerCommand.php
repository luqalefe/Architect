<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Console\Concerns\ParsesModuleAndName;
use LaravelModulesArch\Support\ManifestUpdater;
use LaravelModulesArch\Support\ModuleNaming;
use LaravelModulesArch\Support\StubRenderer;

/**
 * Generates an Anti-Corruption Layer listener under
 * `Modules/{Module}/Infrastructure/ACL/`, declares the subscription in the
 * module's `events.subscribes` manifest entry, AND wires the listener in the
 * ServiceProvider between the `// @arch-listeners-start` /
 * `// @arch-listeners-end` markers.
 *
 * Refuses if `--for` points to a Domain Event under the source module's
 * `Domain/Events/` folder — cross-module subscribers must consume Integration
 * Events from `Contracts/Events/` only.
 *
 * All three side effects (file, manifest, SP) are idempotent: re-running with
 * `--force` won't duplicate the manifest entry or the Event::listen() call.
 */
class MakeAclListenerCommand extends Command
{
    use ParsesModuleAndName;

    protected $signature = 'arch:make-acl-listener
                            {name : Module/ListenerName, e.g. Sale/HandleLeadConverted}
                            {--for= : The integration event handled by this listener (Module/EventName, e.g. Crm/LeadConverted)}
                            {--force : Overwrite the listener file if it already exists}';

    protected $description = 'Generate an ACL listener and wire it to an Integration Event from another module.';

    private const LISTENERS_END_MARKER = '// @arch-listeners-end';

    public function handle(Filesystem $files, StubRenderer $renderer, ManifestUpdater $manifest): int
    {
        $argument = $this->argument('name');
        if (! is_string($argument) || $argument === '') {
            $this->components->error('Argument {name} is required and must be in Module/ListenerName format.');

            return self::FAILURE;
        }

        $parsed = $this->parseModuleAndName($argument);
        if ($parsed === null) {
            return self::FAILURE;
        }
        [$module, $name] = $parsed;

        $forOption = $this->option('for');
        if (! is_string($forOption) || $forOption === '') {
            $this->components->error('Option --for=Module/EventName is required (e.g. --for=Crm/LeadConverted).');

            return self::FAILURE;
        }

        $parsedFor = $this->parseModuleAndName($forOption);
        if ($parsedFor === null) {
            return self::FAILURE;
        }
        [$forModule, $forEvent] = $parsedFor;

        $modulesPath = (string) config('modules-arch.modules_path');
        $modulePath = $modulesPath.DIRECTORY_SEPARATOR.$module;

        if (! $files->isDirectory($modulePath)) {
            $this->components->error("Module {$module} not found at {$modulePath}. Run 'arch:make-module {$module}' first.");

            return self::FAILURE;
        }

        $domainEventPath = $modulesPath.'/'.$forModule.'/Domain/Events/'.$forEvent.'.php';
        if ($files->exists($domainEventPath)) {
            $this->components->error(
                "{$forEvent} is a Domain Event (private to {$forModule}). ACL listeners must subscribe to ".
                "Integration Events under {$forModule}/Contracts/Events/."
            );

            return self::FAILURE;
        }

        $eventFqcn = 'Modules\\'.$forModule.'\\Contracts\\Events\\'.$forEvent;
        $listenerFqcn = 'Modules\\'.$module.'\\Infrastructure\\ACL\\'.$name;

        $replacements = ModuleNaming::replacements($module, $name);
        $replacements['EVENT_FQCN'] = $eventFqcn;
        $replacements['EVENT_CLASS'] = $forEvent;

        $outputPath = $modulePath.'/Infrastructure/ACL/'.$name.'.php';
        if ($files->exists($outputPath) && ! (bool) $this->option('force')) {
            $this->components->error("ACL Listener already exists at {$outputPath}. Use --force to overwrite.");

            return self::FAILURE;
        }

        $stubs = dirname(__DIR__, 3).'/resources/stubs';
        $files->ensureDirectoryExists(dirname($outputPath));
        $files->put($outputPath, $renderer->renderFile($stubs.'/acl/AclListener.stub', $replacements));
        $this->components->info("ACL Listener created: {$outputPath}");

        $manifestResult = $manifest->addToList($modulePath.'/module.json', 'events.subscribes', $eventFqcn);
        if (! $manifestResult->isValid()) {
            $this->components->error('module.json failed validation after update: '.$manifestResult->validation->summary());

            return self::FAILURE;
        }
        $this->components->info($manifestResult->wasAdded()
            ? "module.json updated: events.subscribes += {$eventFqcn}"
            : "module.json already contained {$eventFqcn} in events.subscribes — skipped.");

        $this->wireListener($files, $modulePath, $module, $eventFqcn, $listenerFqcn);

        return self::SUCCESS;
    }

    private function wireListener(
        Filesystem $files,
        string $modulePath,
        string $module,
        string $eventFqcn,
        string $listenerFqcn,
    ): void {
        $spPath = $modulePath.'/Infrastructure/Providers/'.$module.'ServiceProvider.php';

        if (! $files->exists($spPath)) {
            $this->components->warn("ServiceProvider not found at {$spPath} — listener not auto-wired.");

            return;
        }

        $contents = $files->get($spPath);

        if (str_contains($contents, $listenerFqcn.'::class')) {
            $this->components->warn("Listener {$listenerFqcn} already wired in {$module}ServiceProvider — skipping.");

            return;
        }

        if (! str_contains($contents, self::LISTENERS_END_MARKER)) {
            $this->components->warn(self::LISTENERS_END_MARKER." marker not found in {$module}ServiceProvider — wire listener manually.");

            return;
        }

        $indent = '        ';
        $insertion = $indent.'\\Illuminate\\Support\\Facades\\Event::listen('."\n"
            .$indent.'    \\'.$eventFqcn.'::class,'."\n"
            .$indent.'    \\'.$listenerFqcn.'::class,'."\n"
            .$indent.');'."\n";

        $updated = str_replace(
            $indent.self::LISTENERS_END_MARKER,
            $insertion.$indent.self::LISTENERS_END_MARKER,
            $contents,
        );

        $files->put($spPath, $updated);
        $this->components->info("Listener wired in {$module}ServiceProvider.");
    }
}
