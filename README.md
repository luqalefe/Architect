# laravel-modules-arch

DDD-aware modular architecture for Laravel, built on top of [`nwidart/laravel-modules`](https://github.com/nWidart/laravel-modules).

> Status: **in active development**. See [SPRINTS.md](./SPRINTS.md) for the incremental roadmap and [extension_complete_reference.md](./extension_complete_reference.md) for the full design spec.

## Installation

```bash
composer require luq/laravel-modules-arch
```

```bash
php artisan vendor:publish --tag=modules-arch-config
```

## Quick Start

```bash
# Scaffold a Bounded Context with the full DDD folder layout
php artisan arch:make-module Sale

# Validate that no module violates DDD boundaries
php artisan arch:check-boundaries --strict
```

> The commands above are added across increments — see [SPRINTS.md](./SPRINTS.md) for what is currently implemented.

## Documentation

| Document | Purpose |
|---|---|
| [Design Spec](./extension_complete_reference.md) | Every feature mapped to the DDD principle it implements |
| [DDD Structure Explained](./ddd_structure_explained.md) | Anatomy of a module — folder by folder |
| [Roadmap](./SPRINTS.md) | What ships in which increment |

## Development

```bash
composer install
composer test
composer stan
composer format:check
```

## License

MIT
