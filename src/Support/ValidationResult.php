<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

/**
 * Result of validating a payload against {@see ModuleJsonSchema}.
 */
final readonly class ValidationResult
{
    /**
     * @param  list<string>  $errors  Human-readable messages, one per violation.
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function summary(): string
    {
        if ($this->valid) {
            return 'module.json is valid.';
        }

        return "module.json is invalid:\n - ".implode("\n - ", $this->errors);
    }
}
