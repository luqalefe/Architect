<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Concerns;

use Illuminate\Console\Command;

/**
 * Parses the `Module/Name` argument shared by every arch:make-* generator
 * and surfaces consistent error messages.
 *
 * Designed for traits hosted on {@see Command} subclasses —
 * relies on `$this->components` for output.
 */
trait ParsesModuleAndName
{
    /**
     * @return array{string, string}|null [Module, Name] tuple, or null when invalid.
     */
    protected function parseModuleAndName(string $argument): ?array
    {
        if (substr_count($argument, '/') !== 1) {
            $this->components->error("Argument must use 'Module/Name' format (e.g. Sale/SaleOrder). Got: '{$argument}'.");

            return null;
        }

        [$module, $name] = explode('/', $argument, 2);

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $module) !== 1) {
            $this->components->error("Module must be StudlyCase. Got: '{$module}'.");

            return null;
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            $this->components->error("Name must be StudlyCase. Got: '{$name}'.");

            return null;
        }

        return [$module, $name];
    }
}
