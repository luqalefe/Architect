<?php

declare(strict_types=1);

namespace LaravelModulesArch\Testing;

use Nwidart\Modules\Contracts\RepositoryInterface;

/**
 * Pure-PHP helper that does the actual enable/disable dance against the
 * nwidart modules repository. Lives outside {@see IsolatedModuleTest} so the
 * behaviour is unit-testable without booting a Testbench TestCase.
 */
final class ModuleIsolation
{
    /**
     * Disables every module that is NOT in $enabled, enables every module that
     * IS in $enabled, and returns the previous enabled state so it can be
     * restored later.
     *
     * @param  list<string>  $enabled
     * @return array<string, bool>
     */
    public static function apply(RepositoryInterface $repository, array $enabled): array
    {
        $previous = [];

        foreach ($repository->all() as $name => $module) {
            $previous[$name] = $module->isEnabled();

            if (in_array($name, $enabled, true)) {
                $module->enable();
            } else {
                $module->disable();
            }
        }

        return $previous;
    }

    /**
     * Restores each module to the state captured by {@see self::apply()}.
     *
     * @param  array<string, bool>  $previousState
     */
    public static function restore(RepositoryInterface $repository, array $previousState): void
    {
        foreach ($previousState as $name => $wasEnabled) {
            $module = $repository->find($name);
            if ($module === null) {
                continue;
            }

            if ($wasEnabled) {
                $module->enable();
            } else {
                $module->disable();
            }
        }
    }
}
