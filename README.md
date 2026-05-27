# laravel-modules-arch

**DDD-aware modular architecture for Laravel**, on top of [`nwidart/laravel-modules`](https://github.com/nWidart/laravel-modules).

Adds the missing pieces: pure Domain entities, Value Objects, Repositories, Application Services, Anti-Corruption Layers, Integration Events, a `module.json` schema, scaffolding commands for every artifact, and an enforcement command that fails CI when a module reaches across a boundary it shouldn't.

> Status: **v1.0.0**. 175 tests, PHPStan level 8, Pint clean. Roadmap in [SPRINTS.md](./SPRINTS.md).

---

## Why

`nwidart/laravel-modules` gives you folders for "modules" but says nothing about what goes inside them. The result is usually fat controllers, Eloquent-as-everything, and `Modules\Sale` reaching directly into `Modules\Crm\Models\Lead` until the modules stop being modules.

This package picks the conventions from three books:
- **Evans**, *Domain-Driven Design* (Blue Book)
- **Vernon**, *Implementing Domain-Driven Design* (Red Book)
- **Roose / Spatie**, *Laravel Beyond CRUD* (orange book)

…and gives you the tooling to live by them: generators for every artifact, a `module.json` manifest that declares what the module publishes and listens to, and a `arch:check-boundaries` command that enforces R1-R6 in CI.

---

## Installation

```bash
composer require luq/laravel-modules-arch
php artisan vendor:publish --tag=modules-arch-config
```

PHP 8.2+, Laravel 11.x or 12.x.

---

## Quick start (5-minute tour)

```bash
# 1. Scaffold a Bounded Context with the full DDD layout.
php artisan arch:make-module Sale

# 2. Fill in the Domain.
php artisan arch:make-value-object Sale/OrderTotal
php artisan arch:make-enum         Sale/OrderStatus
php artisan arch:make-entity       Sale/SaleOrder
php artisan arch:make-event        Sale/SaleOrderCompleted
php artisan arch:make-exception    Sale/InvalidOrderTransitionException

# 3. Add the Application layer.
php artisan arch:make-action    Sale/CreateSaleOrder
php artisan arch:make-dto       Sale/CreateSaleOrderData
php artisan arch:make-validator Sale/CreateSaleOrder

# 4. Wire persistence. Generates the Repository interface + Eloquent impl
#    AND inserts the $this->app->bind() in your ServiceProvider for you.
php artisan arch:make-repository Sale/SaleOrder

# 5. Publish a public contract for OTHER modules to depend on. Updates
#    module.json → contracts.publishes automatically.
php artisan arch:make-contract Sale/SaleOrderContract

# 6. Publish a cross-module event + subscribe to another module's event
#    through an Anti-Corruption Layer.
php artisan arch:make-integration-event Sale/SaleOrderCompleted
php artisan arch:make-acl-listener      Sale/HandleLeadConverted --for=Crm/LeadConverted

# 7. Check the boundaries in CI.
php artisan arch:check-boundaries --strict
```

The full result of those commands lives in [examples/Modules/Sale/](./examples/Modules/Sale/) — that module is automatically asserted against `arch:check-boundaries --strict` in the package's own test suite.

---

## Commands

| Command | Layer | Effect |
|---|---|---|
| `arch:make-module {Name} {--mode=}` | scaffold | Full DDD folder layout + `module.json` + ServiceProvider |
| `arch:make-entity {Module/Name}` | Domain | `final class` with `pullDomainEvents()` |
| `arch:make-value-object {Module/Name}` | Domain | `final readonly` with `equals()` |
| `arch:make-enum {Module/Name}` | Domain | Backed enum with `canTransitionTo()` |
| `arch:make-state {Module/Name}` | Domain | Same as enum + throwing `transitionTo()` |
| `arch:make-event {Module/Name}` | Domain | Internal POPO (NOT an Illuminate event) |
| `arch:make-exception {Module/Name}` | Domain | `extends DomainException` with named constructors |
| `arch:make-repository {Module/Name}` | Domain + Infra | Interface + Eloquent impl + SP binding |
| `arch:make-action {Module/Name}` | Application | Use Case stub |
| `arch:make-dto {Module/Name}` | Application | `final readonly` with `fromRequest()` + `fromArray()` |
| `arch:make-view-model {Module/Name}` | Application | Method-based ViewModel |
| `arch:make-validator {Module/Name}` | Application | Static `rules()` (`{Name}Rules.php`) |
| `arch:make-contract {Module/Name}` | Contracts | Public interface + `module.json → contracts.publishes` |
| `arch:make-integration-event {Module/Name}` | Contracts | Public event + `module.json → events.publishes` |
| `arch:make-acl-listener {Module/Name} --for=Module/Event` | Infra/ACL | Listener + `events.subscribes` + `Event::listen()` |
| `arch:check-boundaries {--module=} {--strict}` | enforcement | Runs R1-R6 against every scanned file |

`--mode=pragmatic` (default) skips the pure-PHP Domain folders (`Domain/Entities`, `Domain/ValueObjects`, `Domain/Repositories`) so the Eloquent Model can double as the rich entity. `--mode=purist` generates the full layout and turns on R4.

---

## The architecture

```
Modules/Sale/
├── Contracts/                ← PUBLIC API (only thing other modules may import)
│   ├── SaleOrderContract.php
│   └── Events/SaleOrderCompleted.php
├── Domain/                   ← Pure PHP, no Illuminate
│   ├── Entities/SaleOrder.php
│   ├── ValueObjects/OrderTotal.php
│   ├── Enums/OrderStatus.php
│   ├── Events/SaleOrderCompleted.php       ← INTERNAL
│   ├── Exceptions/InvalidOrderTransitionException.php
│   └── Repositories/SaleOrderRepositoryInterface.php
├── Application/              ← Orchestration (no business rules)
│   ├── Actions/CreateSaleOrder.php
│   ├── DTOs/CreateSaleOrderData.php
│   ├── ViewModels/SaleOrderIndexViewModel.php
│   └── Validators/CreateSaleOrderRules.php
├── Infrastructure/           ← Framework-coupled
│   ├── ACL/HandleLeadConverted.php
│   ├── Http/{Controllers,Requests,Resources}
│   ├── Persistence/{Models,Repositories}
│   └── Providers/SaleServiceProvider.php
└── module.json               ← Context Map declaration
```

The dependency arrow always points **inward**: Infrastructure → Application → Domain. `arch:check-boundaries` makes that arrow a hard rule.

---

## The 6 boundary rules

| Code | Severity | Check |
|---|---|---|
| **R1** | error | Cross-module imports only via `Modules\X\Contracts\…` |
| **R2** | error | `Domain/` must not import `Infrastructure/` or `Illuminate\…` (with `ignored_namespaces` allowlist) |
| **R3** | error | `Domain/` must not import `Application/` |
| **R4** | error (off by default) | `Application/` must not import `Infrastructure/` — enable for purist projects |
| **R5** | error | An ACL listener importing `Modules\Other\Contracts\Events\…` must declare it in `module.json → events.subscribes` |
| **R6** | warning | Heuristic: a `*RepositoryInterface` for a class ending in `Item`/`Line`/`Detail`/`Entry`/`Position`/`Step` is probably a child entity (should live behind its Aggregate Root) |

Toggle each rule individually in `config/modules-arch.php`.

---

## Testing utilities

```php
use LaravelModulesArch\Testing\IsolatedModuleTest;
use LaravelModulesArch\Testing\Concerns\MocksCrossModuleActions;
use LaravelModulesArch\Testing\Concerns\AssertsModuleBoundaries;

class SaleModuleTest extends IsolatedModuleTest
{
    use MocksCrossModuleActions, AssertsModuleBoundaries;

    protected array $enabledModules = ['Base', 'Sale'];   // Crm stays OFF for this test

    public function test_sale_creates_an_order_without_touching_crm(): void
    {
        $this->fakeCrossModule('Account', GetSummary::class, ['balance' => 0]);

        // … exercise the Sale module …

        $this->assertModuleHasNoBoundaryErrors('Sale');
    }
}
```

| Artifact | Purpose |
|---|---|
| `IsolatedModuleTest` (abstract class) | Disables every module not in `$enabledModules`; restores after the test |
| `MocksCrossModuleActions` (trait) | `fakeCrossModule(module, action, returnValue)` — marks module enabled + binds a stub action |
| `AssertsModuleBoundaries` (trait) | `assertModuleHasNoBoundaryErrors(?module)` — runs `BoundaryAnalyzer` programmatically |

---

## Documentation

| Document | Purpose |
|---|---|
| [Design Spec](./extension_complete_reference.md) | Every feature mapped to the DDD principle it implements |
| [DDD Structure Explained](./ddd_structure_explained.md) | Anatomy of a module — folder by folder |
| [Roadmap](./SPRINTS.md) | The 9 incremental sprints that built v1.0.0 |
| [Example module](./examples/Modules/Sale/) | Full reference Sale module — passes `check-boundaries --strict` |
| [CHANGELOG](./CHANGELOG.md) | Per-release notes |

---

## Development

```bash
composer install
composer test       # PHPUnit
composer stan       # PHPStan level 8 (Larastan + phpstan-mockery)
composer format     # Laravel Pint
```

See [CONTRIBUTING.md](./CONTRIBUTING.md) for the full local setup.

---

## License

MIT — see [LICENSE](./LICENSE).
