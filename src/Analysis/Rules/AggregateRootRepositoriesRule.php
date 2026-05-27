<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis\Rules;

use LaravelModulesArch\Analysis\AnalysisContext;
use LaravelModulesArch\Analysis\ModuleFile;
use LaravelModulesArch\Analysis\Rule;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Severity;

/**
 * R6 — Repositories should exist only for Aggregate Roots, not internal child
 * entities. Heuristic check: warns when a Repository is generated for a class
 * whose name ends in a typical child-entity suffix (Item, Line, Detail, Entry,
 * Position, Step).
 *
 * Emits warnings, never errors — the heuristic can have false positives.
 */
final class AggregateRootRepositoriesRule implements Rule
{
    private const CHILD_SUFFIXES = ['Item', 'Line', 'Detail', 'Entry', 'Position', 'Step'];

    public function code(): string
    {
        return 'R6';
    }

    public function name(): string
    {
        return 'Repositories only for Aggregate Roots';
    }

    public function evaluate(ModuleFile $file, AnalysisContext $context): array
    {
        if (! str_starts_with($file->relativePath, 'Domain/Repositories/')) {
            return [];
        }
        if (! str_ends_with($file->path, 'RepositoryInterface.php')) {
            return [];
        }

        $entityName = basename($file->path, 'RepositoryInterface.php');

        foreach (self::CHILD_SUFFIXES as $suffix) {
            if ($entityName === $suffix) {
                continue;
            }
            if (! str_ends_with($entityName, $suffix)) {
                continue;
            }

            return [new RuleResult(
                severity: Severity::Warning,
                rule: $this->code(),
                file: $file->path,
                line: 1,
                message: sprintf(
                    "%s looks like a child entity (suffix '%s'). Repositories should exist only for Aggregate Roots.",
                    $entityName,
                    $suffix,
                ),
                suggestion: sprintf(
                    'Expose %s through its parent aggregate (e.g. $parent->%ss()) instead of a dedicated repository.',
                    $entityName,
                    lcfirst($entityName),
                ),
            )];
        }

        return [];
    }
}
