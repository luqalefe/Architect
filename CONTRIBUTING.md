# Contributing

Thanks for considering a contribution. This document covers the local setup
and the conventions enforced by CI.

## Local setup

```bash
git clone https://github.com/luqalefe/Architect.git laravel-modules-arch
cd laravel-modules-arch
composer install
```

Requirements:
- PHP 8.2 or newer
- Composer 2.x

## Running the checks CI runs

```bash
composer test            # PHPUnit (175+ tests, in-memory SQLite via Testbench)
composer stan            # PHPStan level 8 (Larastan + phpstan-mockery, --memory-limit=1G)
composer format          # Apply Laravel Pint
composer format:check    # Check Pint without writing
```

Every PR must keep all four green.

## Adding a new feature

The package is organised by the incremental sprint that introduced it
(see [SPRINTS.md](./SPRINTS.md)). When you add functionality:

1. **Pick the right namespace.**
   - `src/Console/Commands/` for new Artisan commands.
   - `src/Support/` for stateless helpers.
   - `src/Analysis/` for boundary-checking machinery.
   - `src/Testing/` for utilities the package's *users* call in their own tests.
   - `resources/stubs/` for any new `.stub` files.

2. **Mirror the structure in `tests/`.** Unit tests under `tests/Unit/<MatchingNamespace>/`, feature tests under `tests/Feature/<MatchingNamespace>/`. Each new public method gets a happy-path test and at least one edge-case test.

3. **Update `CHANGELOG.md`.** Add your entry under `## [Unreleased]` in the appropriate section (`Added`, `Changed`, `Fixed`, `Removed`).

4. **Update the README** if you add a command or a public testing utility — the tables in the README must stay in sync.

## Conventions

- **Type strict everywhere.** Every PHP file starts with `declare(strict_types=1);` (Pint enforces this).
- **No `@phpstan-ignore` or baseline entries** for new code. If a type is wrong, fix the type — don't suppress.
- **Stubs are not analysed** by PHPStan (they're templates with `{{ TOKEN }}` placeholders); they only need to be valid PHP after token substitution.
- **PT-BR is fine in commit messages**, English in code/docs/stub comments.

## Commit messages

Conventional Commits, with an increment tag in the scope where applicable:

```
feat(i6): arch:check-boundaries com regras R1-R6
fix: ImportExtractor agora suporta grouped use
docs: explain ignored_namespaces in the README
test: cover R5 when manifest lacks events block
```

## Reporting issues

Open an issue on GitHub with:
- the command you ran,
- the expected vs. actual output,
- your PHP / Laravel / `nwidart/laravel-modules` versions.
