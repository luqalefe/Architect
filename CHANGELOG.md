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
