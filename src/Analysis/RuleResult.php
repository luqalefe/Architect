<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

/**
 * A single violation (or warning) detected by a {@see Rule}.
 */
final readonly class RuleResult
{
    public function __construct(
        public Severity $severity,
        public string $rule,
        public string $file,
        public int $line,
        public string $message,
        public ?string $suggestion = null,
    ) {}

    public function isError(): bool
    {
        return $this->severity === Severity::Error;
    }
}
