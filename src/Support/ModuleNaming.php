<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

use Illuminate\Support\Str;

/**
 * Single source of truth for the replacement tokens shared by every stub —
 * module-level (module.json, ServiceProvider) and artifact-level (Entity,
 * ValueObject, Repository, …).
 *
 * Semantics:
 *   - MODULE         : the Bounded Context, always StudlyCase.
 *   - NAME           : the file being generated. For module-creation stubs
 *                      this equals MODULE; for artifact stubs it's the
 *                      artifact class name (e.g. SaleOrder).
 *   - NAMESPACE      : the PHP namespace prefix for the module.
 *   - NAMESPACE_JSON : the same prefix with backslashes escaped for JSON content.
 *   - ALIAS          : snake_case form of MODULE.
 *   - DESCRIPTION    : default human-readable summary used in module.json.
 */
final class ModuleNaming
{
    /**
     * @return array<string, string>
     */
    public static function replacements(string $module, ?string $name = null): array
    {
        $namespace = 'Modules\\'.$module;

        return [
            'MODULE' => $module,
            'NAME' => $name ?? $module,
            'NAMESPACE' => $namespace,
            'NAMESPACE_JSON' => str_replace('\\', '\\\\', $namespace),
            'ALIAS' => Str::snake($module),
            'DESCRIPTION' => $module.' bounded context.',
        ];
    }
}
