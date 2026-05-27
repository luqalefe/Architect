<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

/**
 * Cross-file analysis state shared with every rule invocation:
 * each module's decoded module.json + the package's "ignored namespaces" list.
 */
final readonly class AnalysisContext
{
    /**
     * @param  array<string, array<string, mixed>>  $manifests  module name => decoded module.json contents.
     * @param  list<string>  $ignoredNamespaces  Namespace prefixes (with trailing `\\`) that rules MUST skip.
     */
    public function __construct(
        public array $manifests,
        public array $ignoredNamespaces,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function manifestFor(string $module): array
    {
        return $this->manifests[$module] ?? [];
    }

    public function isIgnored(string $namespace): bool
    {
        foreach ($this->ignoredNamespaces as $prefix) {
            if (str_starts_with($namespace, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
