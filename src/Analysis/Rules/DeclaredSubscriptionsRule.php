<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis\Rules;

use LaravelModulesArch\Analysis\AnalysisContext;
use LaravelModulesArch\Analysis\ModuleFile;
use LaravelModulesArch\Analysis\Rule;
use LaravelModulesArch\Analysis\RuleResult;
use LaravelModulesArch\Analysis\Severity;

/**
 * R5 — Cross-module event imports inside an ACL listener must be declared in
 * the module's module.json → events.subscribes list.
 *
 * Keeps the Context Map honest: every event the module reacts to is visible
 * in the manifest, not just buried in PHP imports.
 */
final class DeclaredSubscriptionsRule implements Rule
{
    public function code(): string
    {
        return 'R5';
    }

    public function name(): string
    {
        return 'Subscribed events must be declared in module.json';
    }

    public function evaluate(ModuleFile $file, AnalysisContext $context): array
    {
        if (! str_starts_with($file->relativePath, 'Infrastructure/ACL/')) {
            return [];
        }

        $manifest = $context->manifestFor($file->module);
        $subscribesRaw = $manifest['events']['subscribes'] ?? [];
        $subscribes = is_array($subscribesRaw)
            ? array_values(array_filter($subscribesRaw, is_string(...)))
            : [];

        $violations = [];

        foreach ($file->imports as $import) {
            if (preg_match('/^Modules\\\\[^\\\\]+\\\\Contracts\\\\Events\\\\[^\\\\]+$/', $import->namespace) !== 1) {
                continue;
            }
            if (str_starts_with($import->namespace, 'Modules\\'.$file->module.'\\')) {
                // Own integration events — not a cross-module subscription.
                continue;
            }
            if (in_array($import->namespace, $subscribes, true)) {
                continue;
            }

            $violations[] = new RuleResult(
                severity: Severity::Error,
                rule: $this->code(),
                file: $file->path,
                line: $import->line,
                message: sprintf(
                    "Listener subscribes to '%s' but it is not declared in module.json → events.subscribes.",
                    $import->namespace,
                ),
                suggestion: 'Add the event FQCN to events.subscribes (or generate the listener with arch:make-acl-listener so the manifest is updated automatically).',
            );
        }

        return $violations;
    }
}
