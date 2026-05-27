# Changelog

All notable changes to `laravel-modules-arch` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `docs/` directory with deep-dive guides: `configuration.md` (full config reference + recipes), `rules.md` (R1-R6 with valid/invalid examples), `generators.md` (catalogue of all 15 `arch:make-*` commands with output paths and side effects), `testing.md` (full scenarios for the three testing utilities).
- README now links the new `docs/` guides under a dedicated "Deep dives" section.
- `SPRINTS.md` status header showing all 9 increments concluded with their commit hashes.

## [1.0.0] - 2026-05-27

First stable release. Covers the full MVP scoped in [SPRINTS.md](./SPRINTS.md)
(increments 0 through 8): primitives, generators for every layer,
cross-context coordination, boundary enforcement, testing utilities, and a
reference Sale + Crm module that passes `arch:check-boundaries --strict`.

**Headlines:**
- 16 Artisan commands covering scaffolding (15× `arch:make-*`) and
  enforcement (`arch:check-boundaries`).
- 6 boundary rules (R1-R6) shipping in pragmatic-default config; toggleable
  individually.
- `module.json` schema (JSON Schema draft-07) with permissive root for
  nwidart-specific keys.
- Reference `examples/Modules/Sale/` + minimal `examples/Modules/Crm/`,
  asserted in CI to pass `arch:check-boundaries --strict`.
- Testing utilities (`IsolatedModuleTest`, `MocksCrossModuleActions`,
  `AssertsModuleBoundaries`) so apps using the package can write tests in
  the same idioms.
- CI matrix: PHP 8.2/8.3/8.4 × Laravel 11/12.

### Added — Increment 0 (Package Skeleton)
- Composer package definition with PSR-4 autoload (`LaravelModulesArch\` → `src/`).
- `LaravelModulesArchServiceProvider` registered via Laravel auto-discovery.
- Publishable config file `config/modules-arch.php` with full schema (modes, boundary rules, ignored namespaces).
- Testbench-based `TestCase` and sanity tests confirming config loads.
- Tooling: PHPUnit, PHPStan (level 8 via Larastan), Pint, GitHub Actions CI matrix (PHP 8.2/8.3/8.4 × Laravel 11/12).

### Added — Increment 1 (Modularization Primitives)
- `LaravelModulesArch\Relations\NullRelation` — real Eloquent relation that returns an empty `Collection` without issuing any SQL (overrides `getEager()` to prevent the second query during eager loading).
- `LaravelModulesArch\Support\ModuleAwareRelation` — static helper with `hasMany`, `hasOne`, `belongsTo`, `morphMany`, `morphOne`, `morphTo`; gracefully collapses to `NullRelation` when the target module is disabled or missing.
- `LaravelModulesArch\Concerns\RegistersModuleMorphMap` — trait so each module's ServiceProvider owns its own morph map slice (additive via `Relation::morphMap()`).
- `LaravelModulesArch\Support\CrossModuleAction` — Open Host Service-style invocation of Actions from other Bounded Contexts, with safe fallback when the module is disabled, the class is missing, or the resolved object has no `handle()`.

### Added — Increment 2 (Module Generator + module.json Schema)
- `arch:make-module {name} {--mode=pragmatic|purist} {--force}` Artisan command that scaffolds a full DDD Bounded Context, layout-aware: pragmatic mode skips `Domain/Entities`, `Domain/ValueObjects`, `Domain/Repositories` and `Infrastructure/Persistence/Repositories`; purist generates them.
- `resources/schemas/module.schema.json` — JSON Schema for module manifests (required `name` + `providers`; `contracts`, `events`, `dependencies` blocks with `additionalProperties: false`; root permissive for nwidart-extra keys like `files` / `keywords`).
- `LaravelModulesArch\Support\ModuleJsonSchema` — wraps justinrainbow/json-schema and returns a `ValidationResult`.
- `LaravelModulesArch\Support\ValidationResult` — typed result with `isValid()`, `errors()`, `summary()`.
- `LaravelModulesArch\Support\StubRenderer` — token-based stub renderer supporting `{{ KEY }}` and `{{KEY}}`, with `render()` and `renderFile()`.
- Module stubs under `resources/stubs/module/`: `module.json.stub`, `composer.json.stub`, `Infrastructure/Providers/ModuleServiceProvider.stub` (uses `RegistersModuleMorphMap`), `routes/web.php.stub`, `routes/api.php.stub`.
- `TestCase::artisanPending()` — typed helper that narrows Laravel's `PendingCommand|int` artisan return.

### Added — Increment 3 (Domain Generators)
- `arch:make-entity {Module/Name}` — generates a `final` Domain Entity stub with `pullDomainEvents()` and a private `recordEvent()`.
- `arch:make-value-object {Module/Name}` — generates an immutable `final readonly` Value Object skeleton with `equals()`.
- `arch:make-enum {Module/Name}` — generates a backed enum with `canTransitionTo()` placeholder.
- `arch:make-event {Module/Name}` — generates a Domain Event POPO (explicitly NOT an Illuminate event — Integration Events live in `Contracts/Events/`).
- `arch:make-exception {Module/Name}` — generates a `final` Domain Exception extending `\DomainException` with a `because()` named constructor.
- `arch:make-repository {Module/EntityName}` — generates BOTH a `Domain/Repositories/{Name}RepositoryInterface.php` AND an `Infrastructure/Persistence/Repositories/Eloquent{Name}Repository.php`, then auto-wires `$this->app->bind(Interface::class, Eloquent::class)` between the `// @arch-bindings-start` / `// @arch-bindings-end` markers in the module's ServiceProvider. Idempotent: re-running with `--force` does not duplicate the binding.
- `LaravelModulesArch\Support\ModuleNaming::replacements()` — single source of truth for the `MODULE` / `NAME` / `NAMESPACE` / `NAMESPACE_JSON` / `ALIAS` / `DESCRIPTION` tokens used across every stub.
- `LaravelModulesArch\Console\Concerns\ParsesModuleAndName` — shared trait that parses the `Module/Name` argument and emits consistent error messages.
- `LaravelModulesArch\Console\Commands\AbstractMakeArtifactCommand` — base class for single-file generators; subclasses only declare `stubPath()`, `outputSubPath()`, `artifactKind()`.

### Added — Increment 4 (Application Generators)
- `arch:make-action {Module/Name}` — generates a `final` Application Service / Use Case stub with a constructor for DI and `handle()` placeholder.
- `arch:make-dto {Module/Name}` — generates a `final readonly` Data Transfer Object with `fromRequest()` and `fromArray()` named factories.
- `arch:make-view-model {Module/Name}` — generates a ViewModel that exposes view-facing methods (not raw properties).
- `arch:make-state {Module/Name}` — generates an enum at `Domain/Enums/` with `canTransitionTo()` AND a throwing `transitionTo()` (richer variant of `arch:make-enum`).
- `arch:make-validator {Module/Name}` — generates `Application/Validators/{Name}Rules.php` (the `Rules` suffix is appended automatically) with a static `rules()` method.

### Added — Increment 5 (Cross-Context Generators)
- `arch:make-contract {Module/Name}` — generates a public `interface` under `Contracts/` AND appends its FQCN to `module.json → contracts.publishes`.
- `arch:make-integration-event {Module/Name}` — generates a `final readonly` POPO under `Contracts/Events/` AND appends to `module.json → events.publishes`.
- `arch:make-acl-listener {Module/Name} --for=Module/EventName` — generates an Anti-Corruption Layer listener under `Infrastructure/ACL/`, appends to `module.json → events.subscribes`, AND wires `Event::listen()` in the ServiceProvider between `// @arch-listeners-start` / `// @arch-listeners-end` markers. Refuses when `--for` targets a Domain Event under `Domain/Events/` (cross-module subscribers must consume Integration Events only). Idempotent on all three side effects.
- `LaravelModulesArch\Support\ManifestUpdater` — appends string entries to dot-paths inside `module.json` idempotently, validating the result via `ModuleJsonSchema` and rewriting pretty-printed.
- `LaravelModulesArch\Support\ManifestUpdateResult` — typed result with `wasAdded()` / `isValid()`.
- `AbstractMakeArtifactCommand::manifestUpdate()` — hook for subclasses to declare a `[dotPath, entry]` tuple, automatically run after the file is generated.

### Added — Increment 6 (Boundary Enforcement)
- `arch:check-boundaries {--module=} {--strict}` — scans every module, runs every enabled rule (R1-R6) against every `.php` file, prints a grouped report and (with `--strict`) exits with code `1` when errors are present. Warnings never fail.
- `R1` (`CrossModuleOnlyViaContractsRule`) — error when a file imports `Modules\Other\NotContracts\...`.
- `R2` (`DomainCannotImportInfrastructureRule`) — error when `Domain/` imports `Modules\X\Infrastructure\...` or any `Illuminate\...` not in the `ignored_namespaces` allowlist.
- `R3` (`DomainCannotImportApplicationRule`) — error when `Domain/` imports `Modules\X\Application\...`.
- `R4` (`ApplicationCannotImportInfrastructureRule`) — error when `Application/` imports `Modules\X\Infrastructure\...`. Off by default (pragmatic mode); enable for purist projects.
- `R5` (`DeclaredSubscriptionsRule`) — error when an ACL listener imports a `Modules\Other\Contracts\Events\...` that isn't declared in `module.json → events.subscribes`.
- `R6` (`AggregateRootRepositoriesRule`) — warning when a `*RepositoryInterface` exists for a class whose name ends in a typical child-entity suffix (`Item`, `Line`, `Detail`, `Entry`, `Position`, `Step`).
- `LaravelModulesArch\Analysis` namespace with the supporting machinery: `Severity` enum, `RuleResult`, `ImportStatement`, `ModuleFile` DTOs, `AnalysisContext`, `ImportExtractor` (nikic/php-parser based; supports both `use Foo\Bar;` and `use Foo\{Bar, Baz};` group syntax), `ModuleScanner` (Symfony Finder, layer detection), `Rule` interface, `RuleRegistry` (config-driven enablement), `BoundaryAnalyzer` (orchestrator).

### Added — Increment 7 (Testing Utilities)
- `LaravelModulesArch\Testing\IsolatedModuleTest` — abstract Testbench test case that disables every module not in `$enabledModules` during setUp and restores the previous state on tearDown. No-op when the nwidart `modules` binding isn't present.
- `LaravelModulesArch\Testing\ModuleIsolation` — pure-PHP helper (apply + restore) that does the actual enable/disable dance. Extracted out of `IsolatedModuleTest` so it's unit-testable without booting a TestCase.
- `LaravelModulesArch\Testing\Concerns\MocksCrossModuleActions` — trait with `fakeCrossModule(module, action, returnValue)` that simultaneously marks the target module as enabled and binds a stub action returning `$returnValue` from `handle()`.
- `LaravelModulesArch\Testing\Concerns\AssertsModuleBoundaries` — trait with `assertModuleHasNoBoundaryErrors(?module)` that runs `BoundaryAnalyzer` programmatically and fails the test (with grouped output) on any R1-R5 error. Warnings (R6) are tolerated.

### Changed
- PHPStan invocation uses `--memory-limit=1G` (the default 128M blew up while booting Larastan).
- `composer.json` requires `justinrainbow/json-schema ^5.3|^6.0` and `nikic/php-parser ^5.0`.
- `composer.json` (dev) now requires `phpstan/phpstan-mockery ^2.0` for proper typing of `Mockery::mock(InterfaceName::class)`.
- Module `ServiceProvider` stub now ships with `// @arch-bindings-start` / `// @arch-bindings-end` markers so `arch:make-repository` can inject bindings deterministically, AND `// @arch-listeners-start` / `// @arch-listeners-end` markers for `arch:make-acl-listener`.
- `TestCase::fakeModules()` callable signature widened to `callable(MockInterface): mixed` so arrow functions with implicit return work without an explicit `void` cast.
