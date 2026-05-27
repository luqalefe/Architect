<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis\Rules;

use LaravelModulesArch\Analysis\AnalysisContext;
use LaravelModulesArch\Analysis\ModuleFile;
use LaravelModulesArch\Analysis\Rule;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Severity;

/**
 * R3 — Domain cannot import Application. Domain is the innermost layer; the
 * dependency arrow points inward (Infrastructure → Application → Domain).
 */
final class DomainCannotImportApplicationRule implements Rule
{
    public function code(): string
    {
        return 'R3';
    }

    public function name(): string
    {
        return 'Domain cannot import Application';
    }

    public function evaluate(ModuleFile $file, AnalysisContext $context): array
    {
        if ($file->layer !== 'Domain') {
            return [];
        }

        $violations = [];
        $ownApp = 'Modules\\'.$file->module.'\\Application\\';

        foreach ($file->imports as $import) {
            if (! str_starts_with($import->namespace, $ownApp)) {
                continue;
            }

            $violations[] = new RuleResult(
                severity: Severity::Error,
                rule: $this->code(),
                file: $file->path,
                line: $import->line,
                message: sprintf("Domain class imports Application: '%s'.", $import->namespace),
                suggestion: 'Invert the dependency: have Application call into Domain, not the other way around.',
            );
        }

        return $violations;
    }
}
