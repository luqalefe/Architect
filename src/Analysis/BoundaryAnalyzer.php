<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

use Illuminate\Filesystem\Filesystem;

/**
 * Top-level orchestrator: scan modules, load manifests, run every enabled rule
 * against every scanned file, aggregate the results.
 */
final class BoundaryAnalyzer
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleScanner $scanner,
    ) {}

    /**
     * @param  list<Rule>  $rules
     * @param  list<string>  $ignoredNamespaces
     * @return list<RuleResult>
     */
    public function analyze(
        string $modulesPath,
        array $rules,
        array $ignoredNamespaces = [],
        ?string $onlyModule = null,
    ): array {
        $files = $this->scanner->scan($modulesPath, $onlyModule);
        $manifests = $this->loadManifests($modulesPath, $files);

        $context = new AnalysisContext(
            manifests: $manifests,
            ignoredNamespaces: $ignoredNamespaces,
        );

        $results = [];
        foreach ($files as $file) {
            foreach ($rules as $rule) {
                foreach ($rule->evaluate($file, $context) as $violation) {
                    $results[] = $violation;
                }
            }
        }

        return $results;
    }

    /**
     * @param  list<ModuleFile>  $files
     * @return array<string, array<string, mixed>>
     */
    private function loadManifests(string $modulesPath, array $files): array
    {
        $modules = array_unique(array_map(fn ($f) => $f->module, $files));
        $manifests = [];

        foreach ($modules as $module) {
            $path = $modulesPath.'/'.$module.'/module.json';
            if (! $this->files->exists($path)) {
                $manifests[$module] = [];

                continue;
            }

            $raw = $this->files->get($path);
            $decoded = json_decode($raw, true);
            $manifests[$module] = is_array($decoded) ? $decoded : [];
        }

        return $manifests;
    }
}
