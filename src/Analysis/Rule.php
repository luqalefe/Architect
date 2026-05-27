<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

/**
 * Contract for a single DDD boundary rule. Each rule receives one file at a
 * time plus the cross-file analysis context, and returns 0..N
 * {@see RuleResult}s describing every violation found in that file.
 */
interface Rule
{
    public function code(): string;

    public function name(): string;

    /**
     * @return list<RuleResult>
     */
    public function evaluate(ModuleFile $file, AnalysisContext $context): array;
}
