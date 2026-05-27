<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

use Illuminate\Console\Command;
use LaravelModulesArch\Analysis\BoundaryAnalyzer;
use LaravelModulesArch\Analysis\RuleRegistry;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Severity;

/**
 * Reports DDD boundary violations across every module (R1-R6) and, when run
 * with --strict, fails the process so CI can gate the merge.
 */
class CheckBoundariesCommand extends Command
{
    protected $signature = 'arch:check-boundaries
                            {--module= : Limit the scan to a single module}
                            {--strict : Exit with code 1 if any errors are found (warnings never fail)}';

    protected $description = 'Run DDD boundary checks against every module (R1-R6).';

    public function handle(BoundaryAnalyzer $analyzer, RuleRegistry $registry): int
    {
        if ((bool) config('modules-arch.boundaries.enabled', true) === false) {
            $this->components->info('Boundary enforcement is disabled (modules-arch.boundaries.enabled = false).');

            return self::SUCCESS;
        }

        $modulesPath = (string) config('modules-arch.modules_path');

        $moduleOption = $this->option('module');
        $onlyModule = is_string($moduleOption) && $moduleOption !== '' ? $moduleOption : null;

        $rulesConfigRaw = config('modules-arch.boundaries.rules', []);
        /** @var array<string, bool> $rulesConfig */
        $rulesConfig = is_array($rulesConfigRaw) ? $rulesConfigRaw : [];

        $ignoredRaw = config('modules-arch.boundaries.ignored_namespaces', []);
        /** @var list<string> $ignored */
        $ignored = is_array($ignoredRaw) ? array_values(array_filter($ignoredRaw, is_string(...))) : [];

        $mode = (string) config('modules-arch.default_mode', 'pragmatic');
        $rules = $registry->enabled(RuleRegistry::resolveForMode($rulesConfig, $mode));

        if (count($rules) === 0) {
            $this->components->warn('No boundary rules are enabled — nothing to check.');

            return self::SUCCESS;
        }

        $results = $analyzer->analyze($modulesPath, $rules, $ignored, $onlyModule);

        $this->display($results, $modulesPath);

        $errors = $this->countErrors($results);

        if ((bool) $this->option('strict') && $errors > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<RuleResult>  $results
     */
    private function display(array $results, string $modulesPath): void
    {
        if (count($results) === 0) {
            $this->components->info('No boundary violations found.');

            return;
        }

        $byModule = [];
        foreach ($results as $result) {
            $relative = $this->relativePath($result->file, $modulesPath);
            $module = explode('/', $relative)[0];
            $byModule[$module][] = [$relative, $result];
        }

        foreach ($byModule as $module => $items) {
            $this->newLine();
            $this->line("<options=bold>{$module}</>");

            foreach ($items as [$relative, $result]) {
                $label = $result->severity === Severity::Error
                    ? '<fg=red>error</>'
                    : '<fg=yellow>warn </>';

                $this->line(sprintf(
                    '  %s  %s:%d  [%s] %s',
                    $label,
                    $relative,
                    $result->line,
                    $result->rule,
                    $result->message,
                ));

                if ($result->suggestion !== null) {
                    $this->line('         <fg=gray>hint: '.$result->suggestion.'</>');
                }
            }
        }

        $errors = $this->countErrors($results);
        $warnings = count($results) - $errors;

        $this->newLine();
        $this->line(sprintf(
            '%d violation%s: %d error%s, %d warning%s.',
            count($results),
            count($results) === 1 ? '' : 's',
            $errors,
            $errors === 1 ? '' : 's',
            $warnings,
            $warnings === 1 ? '' : 's',
        ));
    }

    /**
     * @param  list<RuleResult>  $results
     */
    private function countErrors(array $results): int
    {
        $count = 0;
        foreach ($results as $r) {
            if ($r->isError()) {
                $count++;
            }
        }

        return $count;
    }

    private function relativePath(string $absolute, string $modulesPath): string
    {
        $modulesPath = rtrim($modulesPath, '/').'/';

        if (str_starts_with($absolute, $modulesPath)) {
            return substr($absolute, strlen($modulesPath));
        }

        $real = realpath($modulesPath);
        if ($real !== false && str_starts_with($absolute, $real.'/')) {
            return substr($absolute, strlen($real) + 1);
        }

        return $absolute;
    }
}
