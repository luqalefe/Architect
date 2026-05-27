<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

/**
 * A single namespace imported by a `use` statement in a PHP file.
 */
final readonly class ImportStatement
{
    public function __construct(
        public string $namespace,
        public int $line,
    ) {}
}
