<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis\Rules;

use LaravelModulesArch\Analysis\AnalysisContext;
use LaravelModulesArch\Analysis\ModuleFile;
use LaravelModulesArch\Analysis\Rule;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Severity;

/**
 * R1 — Cross-module imports must go through Contracts/.
 *
 * If a file in Modules\Sale imports Modules\Crm\Domain\Entities\Lead, that's
 * a hard violation: only Modules\Crm\Contracts\* is public API.
 */
final class CrossModuleOnlyViaContractsRule implements Rule
{
    public function code(): string
    {
        return 'R1';
    }

    public function name(): string
    {
        return 'Cross-module imports go through Contracts/ only';
    }

    public function evaluate(ModuleFile $file, AnalysisContext $context): array
    {
        $violations = [];
        $ownPrefix = 'Modules\\'.$file->module.'\\';

        foreach ($file->imports as $import) {
            if (! str_starts_with($import->namespace, 'Modules\\')) {
                continue;
            }
            if (str_starts_with($import->namespace, $ownPrefix)) {
                continue;
            }

            $segments = explode('\\', $import->namespace);
            if (count($segments) < 3) {
                continue;
            }

            $otherModule = $segments[1];
            if ($segments[2] === 'Contracts') {
                continue;
            }

            $violations[] = new RuleResult(
                severity: Severity::Error,
                rule: $this->code(),
                file: $file->path,
                line: $import->line,
                message: sprintf(
                    "Cross-module import '%s' must go through Modules\\%s\\Contracts.",
                    $import->namespace,
                    $otherModule,
                ),
                suggestion: sprintf(
                    'Replace with an import from Modules\\%s\\Contracts\\… (only Contracts/ is public API).',
                    $otherModule,
                ),
            );
        }

        return $violations;
    }
}
