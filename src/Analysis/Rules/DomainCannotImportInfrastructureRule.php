<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis\Rules;

use LaravelModulesArch\Analysis\AnalysisContext;
use LaravelModulesArch\Analysis\ModuleFile;
use LaravelModulesArch\Analysis\Rule;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Severity;

/**
 * R2 — Domain cannot import Infrastructure (Illuminate included, with the
 * config-driven `ignored_namespaces` carve-outs for things like
 * Illuminate\Support and Illuminate\Contracts).
 */
final class DomainCannotImportInfrastructureRule implements Rule
{
    public function code(): string
    {
        return 'R2';
    }

    public function name(): string
    {
        return 'Domain cannot import Infrastructure';
    }

    public function evaluate(ModuleFile $file, AnalysisContext $context): array
    {
        if ($file->layer !== 'Domain') {
            return [];
        }

        $violations = [];
        $ownInfra = 'Modules\\'.$file->module.'\\Infrastructure\\';

        foreach ($file->imports as $import) {
            if (str_starts_with($import->namespace, $ownInfra)) {
                $violations[] = new RuleResult(
                    severity: Severity::Error,
                    rule: $this->code(),
                    file: $file->path,
                    line: $import->line,
                    message: sprintf(
                        "Domain class imports its own Infrastructure: '%s'.",
                        $import->namespace,
                    ),
                    suggestion: 'Move the dependency behind a Domain Repository interface; let Infrastructure depend on Domain, not the reverse.',
                );

                continue;
            }

            if (str_starts_with($import->namespace, 'Illuminate\\') && ! $context->isIgnored($import->namespace)) {
                $violations[] = new RuleResult(
                    severity: Severity::Error,
                    rule: $this->code(),
                    file: $file->path,
                    line: $import->line,
                    message: sprintf(
                        "Domain class imports the framework: '%s'.",
                        $import->namespace,
                    ),
                    suggestion: 'Domain must be framework-agnostic. If you really need this, add the namespace to modules-arch.boundaries.ignored_namespaces.',
                );
            }
        }

        return $violations;
    }
}
