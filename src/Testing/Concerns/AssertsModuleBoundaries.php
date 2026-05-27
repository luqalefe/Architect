<?php

declare(strict_types=1);

namespace LaravelModulesArch\Testing\Concerns;

use Illuminate\Foundation\Application;
use LaravelModulesArch\Analysis\BoundaryAnalyzer;
use LaravelModulesArch\Analysis\RuleRegistry;
use LaravelModulesArch\Analysis\RuleResult;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Lets PHPUnit feature tests run `arch:check-boundaries` programmatically as
 * a single assertion — handy for "this module is well-formed" smoke tests in
 * a project's main test suite, without shelling out to Artisan.
 *
 * Warnings (R6) are tolerated; only errors trigger an assertion failure.
 *
 * @property Application|null $app
 */
trait AssertsModuleBoundaries
{
    protected function assertModuleHasNoBoundaryErrors(?string $module = null): void
    {
        $errors = $this->runBoundaryAnalysis($module);

        $lines = array_map(
            static fn (RuleResult $r) => sprintf('  [%s] %s:%d — %s', $r->rule, $r->file, $r->line, $r->message),
            $errors,
        );

        Assert::assertCount(
            0,
            $errors,
            count($errors) > 0 ? "Module boundary errors detected:\n".implode("\n", $lines) : '',
        );
    }

    /**
     * @return list<RuleResult>
     */
    private function runBoundaryAnalysis(?string $module): array
    {
        $app = $this->bootedApp();

        $analyzer = $app->make(BoundaryAnalyzer::class);
        $registry = $app->make(RuleRegistry::class);

        $modulesPath = (string) config('modules-arch.modules_path');

        $rulesConfigRaw = config('modules-arch.boundaries.rules', []);
        /** @var array<string, bool> $rulesConfig */
        $rulesConfig = is_array($rulesConfigRaw) ? $rulesConfigRaw : [];

        $ignoredRaw = config('modules-arch.boundaries.ignored_namespaces', []);
        /** @var list<string> $ignored */
        $ignored = is_array($ignoredRaw)
            ? array_values(array_filter($ignoredRaw, is_string(...)))
            : [];

        $mode = (string) config('modules-arch.default_mode', 'pragmatic');
        $rules = $registry->enabled(RuleRegistry::resolveForMode($rulesConfig, $mode));

        $results = $analyzer->analyze($modulesPath, $rules, $ignored, $module);

        return array_values(array_filter($results, static fn (RuleResult $r) => $r->isError()));
    }

    private function bootedApp(): Application
    {
        if ($this->app === null) {
            throw new RuntimeException(
                'AssertsModuleBoundaries requires a booted application — hook it into a Laravel/Testbench TestCase.'
            );
        }

        return $this->app;
    }
}
