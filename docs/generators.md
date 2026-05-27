# Generator Catalogue

All 15 `arch:make-*` commands, with their exact output paths, side effects,
and a sample of the generated code.

> All artifact generators use the `Module/Name` argument format. They refuse
> non-StudlyCase names, refuse to overwrite existing files without `--force`,
> and require the target module to already exist (run `arch:make-module {Module}` first).

---

## `arch:make-module`

Scaffolds a full Bounded Context.

```bash
php artisan arch:make-module Sale
php artisan arch:make-module Sale --mode=purist
php artisan arch:make-module Sale --force   # overwrite existing
```

**Generates:**

| Path | Content |
|---|---|
| `Modules/Sale/module.json` | Manifest (name, alias, providers, contracts, events, dependencies) |
| `Modules/Sale/composer.json` | PSR-4 autoload + Laravel auto-discovery |
| `Modules/Sale/Infrastructure/Providers/SaleServiceProvider.php` | Uses `RegistersModuleMorphMap`, ships with `// @arch-bindings-start/end` and `// @arch-listeners-start/end` markers |
| `Modules/Sale/routes/{web,api}.php` | Empty route groups prefixed with the module's alias |
| `Modules/Sale/Domain/{Enums,Events,Exceptions}/.gitkeep` | Empty domain folders |
| `Modules/Sale/Application/{Actions,DTOs,ViewModels,Validators}/.gitkeep` | Empty application folders |
| `Modules/Sale/Infrastructure/{Http/Controllers,Http/Requests,Http/Resources,Persistence/Models,ACL}/.gitkeep` | Empty infra folders |
| `Modules/Sale/database/{migrations,factories,seeders}/.gitkeep` | Empty db folders |
| `Modules/Sale/resources/views/.gitkeep` | Empty views folder |
| `Modules/Sale/tests/{Unit/Domain,Feature/Application}/.gitkeep` | Test scaffolds |
| `Modules/Sale/Contracts/.gitkeep` | Empty contracts folder |

**Mode differences:**
- `--mode=pragmatic` (default): omits `Domain/Entities`, `Domain/ValueObjects`, `Domain/Repositories`, `Infrastructure/Persistence/Repositories`.
- `--mode=purist`: generates all four.

After generation the command validates the produced `module.json` against
the JSON Schema and fails if invalid.

---

## `arch:make-entity`

```bash
php artisan arch:make-entity Sale/SaleOrder
```

**Generates:** `Modules/Sale/Domain/Entities/SaleOrder.php`

```php
final class SaleOrder
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        // TODO: declare domain properties
    ) {}

    public function id(): string { return $this->id; }

    private function recordEvent(object $event): void { $this->domainEvents[] = $event; }

    public function pullDomainEvents(): array { /* ... drains and returns ... */ }
}
```

---

## `arch:make-value-object`

```bash
php artisan arch:make-value-object Sale/OrderTotal
```

**Generates:** `Modules/Sale/Domain/ValueObjects/OrderTotal.php` — `final readonly class` with `equals()` and constructor for invariant validation.

---

## `arch:make-enum`

```bash
php artisan arch:make-enum Sale/OrderStatus
```

**Generates:** `Modules/Sale/Domain/Enums/OrderStatus.php` — `enum X: string` with a `canTransitionTo(self $target): bool` placeholder.

---

## `arch:make-state`

```bash
php artisan arch:make-state Sale/OrderStatus
```

**Generates:** `Modules/Sale/Domain/Enums/OrderStatus.php` — like `arch:make-enum` but also includes a throwing `transitionTo(self $target): self` for state-machine style transitions.

---

## `arch:make-event`

```bash
php artisan arch:make-event Sale/OrderItemAdded
```

**Generates:** `Modules/Sale/Domain/Events/OrderItemAdded.php` — `final readonly` POPO, **explicitly not** an Illuminate event. For cross-module pub/sub use `arch:make-integration-event` instead.

---

## `arch:make-exception`

```bash
php artisan arch:make-exception Sale/InvalidOrderTransitionException
```

**Generates:** `Modules/Sale/Domain/Exceptions/InvalidOrderTransitionException.php` — `final class … extends DomainException` with a `because(string $reason): self` named-constructor starter.

---

## `arch:make-repository`

```bash
php artisan arch:make-repository Sale/SaleOrder
```

**Generates TWO files AND patches one:**

| Path | Content |
|---|---|
| `Modules/Sale/Domain/Repositories/SaleOrderRepositoryInterface.php` | `interface` with `findById`, `save`, `delete` |
| `Modules/Sale/Infrastructure/Persistence/Repositories/EloquentSaleOrderRepository.php` | `final class … implements SaleOrderRepositoryInterface` |
| `Modules/Sale/Infrastructure/Providers/SaleServiceProvider.php` | Adds `$this->app->bind(Interface::class, Eloquent::class)` between `// @arch-bindings-start/end` markers |

The binding insertion is **idempotent**: re-running with `--force` won't add a duplicate `$this->app->bind` for the same interface (it greps for the interface FQCN before inserting).

---

## `arch:make-action`

```bash
php artisan arch:make-action Sale/CreateSaleOrder
```

**Generates:** `Modules/Sale/Application/Actions/CreateSaleOrder.php` — `final class` with `__construct()` for DI and `handle(): mixed` placeholder.

---

## `arch:make-dto`

```bash
php artisan arch:make-dto Sale/CreateSaleOrderData
```

**Generates:** `Modules/Sale/Application/DTOs/CreateSaleOrderData.php` — `final readonly class` with `fromRequest(Request)` and `fromArray(array)` named factories.

---

## `arch:make-view-model`

```bash
php artisan arch:make-view-model Sale/SaleOrderIndexViewModel
```

**Generates:** `Modules/Sale/Application/ViewModels/SaleOrderIndexViewModel.php` — `final class` exposing methods (not properties) for the view to consume.

---

## `arch:make-validator`

```bash
php artisan arch:make-validator Sale/CreateSaleOrder
```

**Generates:** `Modules/Sale/Application/Validators/CreateSaleOrderRules.php`
(note the `Rules` suffix is automatic) — `final class` with static `rules(): array`.

---

## `arch:make-contract`

```bash
php artisan arch:make-contract Sale/SaleOrderContract
```

**Generates AND updates manifest:**

| Effect | Detail |
|---|---|
| File | `Modules/Sale/Contracts/SaleOrderContract.php` — `interface` with class-level docblock explaining it's public API |
| Manifest | Appends `Modules\\Sale\\Contracts\\SaleOrderContract` to `module.json → contracts.publishes` (idempotent — won't duplicate) |

The manifest is re-validated against the schema after the write.

---

## `arch:make-integration-event`

```bash
php artisan arch:make-integration-event Sale/SaleOrderCompleted
```

**Generates AND updates manifest:**

| Effect | Detail |
|---|---|
| File | `Modules/Sale/Contracts/Events/SaleOrderCompleted.php` — `final readonly` POPO |
| Manifest | Appends FQCN to `module.json → events.publishes` |

---

## `arch:make-acl-listener`

```bash
php artisan arch:make-acl-listener Sale/HandleLeadConverted --for=Crm/LeadConverted
```

**The most active command in the suite. Three side effects:**

| Effect | Detail |
|---|---|
| File | `Modules/Sale/Infrastructure/ACL/HandleLeadConverted.php` — `final class` with `use Modules\Crm\Contracts\Events\LeadConverted;` and `handle(LeadConverted $event): void` |
| Manifest | Appends `Modules\\Crm\\Contracts\\Events\\LeadConverted` to `module.json → events.subscribes` |
| ServiceProvider | Inserts `\Illuminate\Support\Facades\Event::listen(\Modules\Crm\…\LeadConverted::class, \Modules\Sale\…\HandleLeadConverted::class);` between `// @arch-listeners-start/end` markers |

**Safety check:** refuses to generate if `--for` points to a Domain Event
(file exists at `Modules/Crm/Domain/Events/LeadConverted.php`) — ACL
listeners must consume Integration Events from `Contracts/Events/` only.

All three side effects are idempotent under `--force`.

---

## `arch:check-boundaries`

The enforcement command. See [docs/rules.md](./rules.md) for what each rule
checks. Recap of the CLI:

```bash
php artisan arch:check-boundaries
php artisan arch:check-boundaries --strict          # exit 1 on errors
php artisan arch:check-boundaries --module=Sale
php artisan arch:check-boundaries --module=Sale --strict
```

`--strict` is the CI flag. `--module=X` narrows the scan (useful for per-PR
checks in big monorepos).

---

## Conventions all generators share

- **Argument format:** `Module/Name` in StudlyCase (`Sale/SaleOrder`,
  `CustomerSupport/CreateTicket`).
- **`--force`:** overwrites existing files but stays idempotent on
  manifest/SP side effects (no duplicate entries).
- **Refusal modes:** non-StudlyCase name, missing module, missing `--for`
  for `arch:make-acl-listener` — all exit with a clear error message and
  non-zero exit code.
- **Output:** every generator prints the path(s) it wrote and any manifest
  / SP updates it performed.
