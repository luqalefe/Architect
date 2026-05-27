<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

use Nwidart\Modules\Facades\Module;
use Throwable;

/**
 * Safely invokes an Action class from another Bounded Context.
 *
 * Honors the Open Host Service pattern: the caller only sees a controlled
 * entry point and a fallback value, never the target module's internal classes.
 * If the target module is disabled, the action class isn't loaded, or the
 * facade can't even resolve the module registry, the `$default` value (or the
 * closure returning it) is returned instead of crashing.
 */
final class CrossModuleAction
{
    /**
     * @param  array<int|string, mixed>  $params  Positional arguments spread into the action's handle() method.
     * @param  mixed|callable():mixed  $default
     */
    public static function run(
        string $module,
        string $action,
        array $params = [],
        mixed $default = null,
    ): mixed {
        if (! self::moduleEnabled($module)) {
            return value($default);
        }

        if (! class_exists($action)) {
            return value($default);
        }

        $instance = app($action);

        if (! is_object($instance) || ! method_exists($instance, 'handle')) {
            return value($default);
        }

        return $instance->handle(...$params);
    }

    private static function moduleEnabled(string $name): bool
    {
        try {
            return Module::isEnabled($name);
        } catch (Throwable) {
            return false;
        }
    }
}
