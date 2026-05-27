# Boundary Rules (R1-R6)

Every rule runs against one `.php` file at a time. The orchestrator
(`BoundaryAnalyzer`) gathers all results and the command groups them by
module for display. With `--strict`, only **errors** fail the exit code;
warnings are informational.

```bash
php artisan arch:check-boundaries           # report only, exit 0
php artisan arch:check-boundaries --strict  # exit 1 on any error
php artisan arch:check-boundaries --module=Sale --strict
```

Each rule is config-driven — see [docs/configuration.md](./configuration.md).

---

## R1 — Cross-module imports only via Contracts/

**Class:** `LaravelModulesArch\Analysis\Rules\CrossModuleOnlyViaContractsRule`
**Severity:** error
**Config key:** `cross_module_via_contracts`

### What it checks

Every `use` statement of the form `Modules\Other\…` (where `Other` is not
the file's own module) must start with `Modules\Other\Contracts\`. Everything
outside `Contracts/` is private to its module.

### Valid

```php
// Modules/Sale/Application/Actions/CreateSaleOrder.php
use Modules\Crm\Contracts\LeadContract;                  // OK — public contract
use Modules\Crm\Contracts\Events\LeadConverted;          // OK — public event
```

### Invalid

```php
// Modules/Sale/Application/Actions/CreateSaleOrder.php
use Modules\Crm\Domain\Entities\Lead;                    // ✗ Domain is private
use Modules\Crm\Infrastructure\Persistence\Models\Lead;  // ✗ Infra is private
use Modules\Crm\Application\Actions\ConvertLead;         // ✗ Application is private
```

### How to fix

Expose the thing you need in `Modules\Crm\Contracts\…` (via
`arch:make-contract` or `arch:make-integration-event`), then import from
there. If you find yourself wanting to import an Entity directly, you
probably want an Integration Event + ACL instead.

---

## R2 — Domain cannot import Infrastructure

**Class:** `LaravelModulesArch\Analysis\Rules\DomainCannotImportInfrastructureRule`
**Severity:** error
**Config key:** `domain_no_infrastructure`

### What it checks

Files under `Modules/X/Domain/` cannot import:
1. `Modules\X\Infrastructure\…` (own Infra), OR
2. `Illuminate\…` **unless** the namespace appears in
   `boundaries.ignored_namespaces` (defaults to `Illuminate\Support\` and
   `Illuminate\Contracts\`).

The Domain is the innermost layer; it must remain framework-agnostic so
you can unit-test it without booting Laravel and swap the persistence
mechanism without changing it.

### Valid

```php
// Modules/Sale/Domain/Entities/SaleOrder.php
use DateTimeImmutable;                                   // OK — PHP stdlib
use Modules\Sale\Domain\ValueObjects\OrderTotal;         // OK — same layer
use Illuminate\Support\Str;                              // OK — allowlisted
```

### Invalid

```php
// Modules/Sale/Domain/Entities/SaleOrder.php
use Illuminate\Database\Eloquent\Model;                  // ✗ framework
use Modules\Sale\Infrastructure\Persistence\Models\X;    // ✗ own Infra
```

### How to fix

- For ORM models in Domain: extract a pure Entity and put it behind a
  Repository interface (generate with `arch:make-repository`).
- For Illuminate types: see if a `Carbon` or stdlib `DateTimeImmutable`
  works instead. If the type truly is unavoidable, add it to
  `ignored_namespaces` deliberately.

---

## R3 — Domain cannot import Application

**Class:** `LaravelModulesArch\Analysis\Rules\DomainCannotImportApplicationRule`
**Severity:** error
**Config key:** `domain_no_application`

### What it checks

Files under `Modules/X/Domain/` cannot import `Modules\X\Application\…`.
This catches accidental dependency inversions where the entity depends on
the use case that operates on it.

### Valid

```php
// Modules/Sale/Domain/Entities/SaleOrder.php
use Modules\Sale\Domain\Events\SaleOrderCompleted;       // OK — same layer
```

### Invalid

```php
// Modules/Sale/Domain/Entities/SaleOrder.php
use Modules\Sale\Application\Actions\CreateSaleOrder;    // ✗
```

### How to fix

Domain Events. The Application layer subscribes to events the entity
records; the entity never knows who's listening.

---

## R4 — Application cannot import Infrastructure (purist only)

**Class:** `LaravelModulesArch\Analysis\Rules\ApplicationCannotImportInfrastructureRule`
**Severity:** error
**Config key:** `application_no_infrastructure`

### What it checks

Files under `Modules/X/Application/` cannot import `Modules\X\Infrastructure\…`.

**Off by default.** In pragmatic mode the Eloquent Model lives in
`Infrastructure/Persistence/Models/` but doubles as the rich entity — and
Actions often reference it directly. That's fine; just keep R4 off.

In purist mode, every persistence call goes through a Domain Repository
interface, and Application never names a concrete Infrastructure class —
turn R4 on to enforce it.

### Valid (purist)

```php
// Modules/Sale/Application/Actions/CreateSaleOrder.php
use Modules\Sale\Domain\Repositories\SaleOrderRepositoryInterface;  // OK
use Modules\Sale\Domain\Entities\SaleOrder;                          // OK
```

### Invalid (purist)

```php
// Modules/Sale/Application/Actions/CreateSaleOrder.php
use Modules\Sale\Infrastructure\Persistence\Models\SaleOrder;        // ✗
```

### How to fix

Inject the Repository interface and let the container resolve the Eloquent
implementation (`arch:make-repository` wires the binding for you).

---

## R5 — Subscribed events must be declared in module.json

**Class:** `LaravelModulesArch\Analysis\Rules\DeclaredSubscriptionsRule`
**Severity:** error
**Config key:** `declared_subscriptions`

### What it checks

Files under `Modules/X/Infrastructure/ACL/` that import
`Modules\Other\Contracts\Events\…` must list those FQCNs in
`module.json → events.subscribes`. Keeps the Context Map honest: every
event the module reacts to is documented in the manifest, not just buried
in PHP imports.

### Valid

```php
// Modules/Sale/Infrastructure/ACL/HandleLeadConverted.php
use Modules\Crm\Contracts\Events\LeadConverted;
```

```json
// Modules/Sale/module.json
{
    "events": {
        "subscribes": [
            "Modules\\Crm\\Contracts\\Events\\LeadConverted"
        ]
    }
}
```

### Invalid

Same listener, manifest missing the entry:

```json
{
    "events": { "subscribes": [] }
}
```

### How to fix

Generate listeners with `arch:make-acl-listener Sale/HandleLeadConverted --for=Crm/LeadConverted` —
the manifest is updated automatically. Or add the FQCN manually to
`events.subscribes`.

---

## R6 — Repositories only for Aggregate Roots (warning)

**Class:** `LaravelModulesArch\Analysis\Rules\AggregateRootRepositoriesRule`
**Severity:** **warning** (never fails `--strict`)
**Config key:** `aggregate_root_repositories`

### What it checks

A heuristic: a `*RepositoryInterface` whose entity name ends in one of
`Item`, `Line`, `Detail`, `Entry`, `Position`, `Step` is *probably* a child
entity that should be accessed through its Aggregate Root, not a
standalone repository.

False positives exist (e.g. `LineItem` might legitimately be a top-level
aggregate in your domain). That's why it's a warning, not an error.

### Triggers

```
Modules/Sale/Domain/Repositories/SaleOrderItemRepositoryInterface.php
                                  ^^^^^^^^^^^^^ ends in "Item"
```

Output:
```
warn  Domain/Repositories/SaleOrderItemRepositoryInterface.php:1
      [R6] SaleOrderItem looks like a child entity (suffix 'Item').
            Repositories should exist only for Aggregate Roots.
      hint: Expose SaleOrderItem through its parent aggregate (e.g. $parent->saleOrderItems())
             instead of a dedicated repository.
```

### How to handle

- **If the warning is right:** delete the child repository, expose the
  entity through the parent (e.g. `$saleOrder->items()` returning a
  collection).
- **If it's a false positive:** disable R6 in your config
  (`'aggregate_root_repositories' => false`).

---

## Adding `arch:check-boundaries` to CI

```yaml
# .github/workflows/ci.yml
- name: Architecture
  run: php artisan arch:check-boundaries --strict
```

A failing rule prints the file, line, rule code, message, and suggestion —
enough to fix without re-running.

For per-PR speed-ups in big projects, narrow the scope:

```bash
php artisan arch:check-boundaries --module=Sale --strict
```
