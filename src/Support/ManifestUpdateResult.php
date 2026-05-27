<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

final readonly class ManifestUpdateResult
{
    public function __construct(
        public bool $added,
        public ValidationResult $validation,
    ) {}

    public function wasAdded(): bool
    {
        return $this->added;
    }

    public function isValid(): bool
    {
        return $this->validation->isValid();
    }
}
