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

### Changed
- PHPStan invocation uses `--memory-limit=1G` (the default 128M blew up while booting Larastan).
