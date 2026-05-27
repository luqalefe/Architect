# Changelog

All notable changes to `laravel-modules-arch` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

### Changed
- PHPStan invocation uses `--memory-limit=1G` (the default 128M blew up while booting Larastan).
- `composer.json` requires `justinrainbow/json-schema ^5.3|^6.0`.
