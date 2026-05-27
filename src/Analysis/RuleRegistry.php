<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

use LaravelModulesArch\Analysis\Rules\AggregateRootRepositoriesRule;
use LaravelModulesArch\Analysis\Rules\ApplicationCannotImportInfrastructureRule;
use LaravelModulesArch\Analysis\Rules\CrossModuleOnlyViaContractsRule;
use LaravelModulesArch\Analysis\Rules\DeclaredSubscriptionsRule;
use LaravelModulesArch\Analysis\Rules\DomainCannotImportApplicationRule;
use LaravelModulesArch\Analysis\Rules\DomainCannotImportInfrastructureRule;

/**
 * Maps the config flags under `modules-arch.boundaries.rules` to concrete
 * {@see Rule} instances, returning only those the user has enabled.
 */
final class RuleRegistry
{
    /** @var array<string, class-string<Rule>> */
    private const RULE_MAP = [
        'cross_module_via_contracts' => CrossModuleOnlyViaContractsRule::class,
        'domain_no_infrastructure' => DomainCannotImportInfrastructureRule::class,
        'domain_no_application' => DomainCannotImportApplicationRule::class,
        'application_no_infrastructure' => ApplicationCannotImportInfrastructureRule::class,
        'declared_subscriptions' => DeclaredSubscriptionsRule::class,
        'aggregate_root_repositories' => AggregateRootRepositoriesRule::class,
    ];

    /**
     * @param  array<string, bool>  $config  The `modules-arch.boundaries.rules` array.
     * @return list<Rule>
     */
    public function enabled(array $config): array
    {
        $rules = [];

        foreach (self::RULE_MAP as $key => $class) {
            if (($config[$key] ?? false) === true) {
                $rules[] = new $class;
            }
        }

        return $rules;
    }

    /**
     * Overlay the architectural mode onto the raw config: in 'purist' mode R4
     * (Application cannot import Infrastructure) is force-enabled, matching the
     * promise made in config/modules-arch.php. Caller-provided values for other
     * rules are preserved verbatim.
     *
     * @param  array<string, bool>  $config
     * @return array<string, bool>
     */
    public static function resolveForMode(array $config, string $mode): array
    {
        if ($mode === 'purist') {
            $config['application_no_infrastructure'] = true;
        }

        return $config;
    }
}
