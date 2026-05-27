<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis\Rules;

use LaravelModulesArch\Analysis\AnalysisContext;
use LaravelModulesArch\Analysis\ModuleFile;
use LaravelModulesArch\Analysis\Rule;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Severity;

/**
 * R4 — Application cannot import Infrastructure (only enforced in purist mode).
 *
 * In pragmatic mode this rule is OFF: Eloquent Models live in Infrastructure
 * but double as the rich entity, so Application/Actions reference them directly.
 */
final class ApplicationCannotImportInfrastructureRule implements Rule
{
    public function code(): string
    {
        return 'R4';
    }

    public function name(): string
    {
        return 'Application cannot import Infrastructure (purist mode)';
    }

    public function evaluate(ModuleFile $file, AnalysisContext $context): array
    {
        if ($file->layer !== 'Application') {
            return [];
        }

        $violations = [];
        $ownInfra = 'Modules\\'.$file->module.'\\Infrastructure\\';

        foreach ($file->imports as $import) {
            if (! str_starts_with($import->namespace, $ownInfra)) {
                continue;
            }

            $violations[] = new RuleResult(
                severity: Severity::Error,
                rule: $this->code(),
                file: $file->path,
                line: $import->line,
                message: sprintf("Application imports Infrastructure: '%s'.", $import->namespace),
                suggestion: 'Depend on a Repository or Service interface declared in Domain instead.',
            );
        }

        return $violations;
    }
}
