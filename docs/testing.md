# Testing Utilities

Three artifacts under `LaravelModulesArch\Testing\` to write tests that
match the package's idioms:

| Artifact | Type | Purpose |
|---|---|---|
| `IsolatedModuleTest` | abstract class | Run a test with only specific modules enabled |
| `MocksCrossModuleActions` | trait | Stub a `CrossModuleAction::run` call without booting the target module |
| `AssertsModuleBoundaries` | trait | Run `arch:check-boundaries` as a single PHPUnit assertion |

---

## `IsolatedModuleTest`

Extends `Orchestra\Testbench\TestCase`. Declares which modules should be
enabled for the test; `setUp()` disables every other module via nwidart's
repository, `tearDown()` restores the previous state.

```php
namespace App\Tests\Sale;

use LaravelModulesArch\Testing\IsolatedModuleTest;

class SaleModuleTest extends IsolatedModuleTest
{
    protected array $enabledModules = ['Base', 'Sale'];

    public function test_sale_works_with_crm_disabled(): void
    {
        // At this point: Base + Sale enabled, everything else (including Crm) off.
        // Module::isEnabled('Crm') returns false here.
        // ModuleAwareRelation::hasMany(..., 'Crm', ...) returns a NullRelation.

        $order = SaleOrder::factory()->create();
        $this->assertDatabaseHas('sale_orders', ['id' => $order->id]);
    }
}
```

**Behaviour when nwidart isn't bound:** the class checks
`$this->app->bound('modules')` first. If `modules` isn't bound (e.g. you're
testing an app that doesn't have nwidart fully wired in the test
environment), it no-ops silently — the test runs without isolation.

**The pure logic lives in `LaravelModulesArch\Testing\ModuleIsolation`** —
unit-testable independently of any TestCase. The abstract class is a thin
wrapper that drives it from `setUp`/`tearDown`.

---

## `MocksCrossModuleActions`

For tests of an Action that calls into another module via
`CrossModuleAction::run()`. The trait gives you `fakeCrossModule()`:

```php
namespace App\Tests\Sale;

use LaravelModulesArch\Testing\Concerns\MocksCrossModuleActions;
use Modules\Account\Actions\GetFinancialSummary;
use Tests\TestCase;

class GetSaleSummaryTest extends TestCase
{
    use MocksCrossModuleActions;

    public function test_includes_account_summary_when_account_is_available(): void
    {
        // Account module doesn't even need to be installed for this test to run.
        $this->fakeCrossModule(
            'Account',
            GetFinancialSummary::class,
            ['balance' => 1000.0, 'currency' => 'BRL'],
        );

        $result = app(GetSaleSummary::class)->handle('customer-1');

        $this->assertSame(1000.0, $result['account']['balance']);
    }
}
```

### What `fakeCrossModule()` does

1. **Marks the target module as enabled.** Binds a Mockery stub to the
   `modules` service in the container that returns `true` for
   `isEnabled($module)`. This means `CrossModuleAction::run` passes its
   module-enabled guard.

2. **Binds a stub action.** Binds the action FQCN in the container to an
   anonymous class whose `handle(...): mixed` returns `$returnValue`. When
   `CrossModuleAction::run` calls `app($action)`, it gets the stub.

### Requirements

- The action class FQCN must autoload (must exist as a real PHP class). The
  helper swaps the *implementation*, not the class definition itself.
- The host class must extend a Laravel TestCase so `$this->app` is set.

### Multiple fakes

```php
$this->fakeCrossModule('Account', GetSummary::class, ['ok']);
$this->fakeCrossModule('Crm',     GetLeadCount::class, 42);
$this->fakeCrossModule('Stock',   GetAvailability::class, false);
```

Each registration is independent; they coexist in the same test.

---

## `AssertsModuleBoundaries`

Run `arch:check-boundaries` programmatically as a single test assertion.
Useful as a "smoke test of architecture" inside the main suite — no need
to shell out to Artisan.

```php
namespace App\Tests\Architecture;

use LaravelModulesArch\Testing\Concerns\AssertsModuleBoundaries;
use Tests\TestCase;

class ModuleBoundariesTest extends TestCase
{
    use AssertsModuleBoundaries;

    public function test_all_modules_pass_boundary_rules(): void
    {
        $this->assertModuleHasNoBoundaryErrors();
    }

    public function test_sale_module_is_clean(): void
    {
        $this->assertModuleHasNoBoundaryErrors('Sale');
    }
}
```

**Failure output** includes the file, line, rule code, and message:

```
Module boundary errors detected:
  [R1] /app/Modules/Sale/Application/Actions/Bad.php:5 — Cross-module
        import 'Modules\Crm\Domain\Entities\Lead' must go through
        Modules\Crm\Contracts.
  [R5] /app/Modules/Sale/Infrastructure/ACL/Foo.php:6 — Listener
        subscribes to 'Modules\Account\Contracts\Events\Charged' but it
        is not declared in module.json → events.subscribes.
```

**Warnings (R6) are tolerated** — only errors fail the assertion. This
matches the `--strict` semantics of the CLI command.

---

## Combining all three

A typical "this module is well-formed and works in isolation" test:

```php
namespace App\Tests\Sale;

use LaravelModulesArch\Testing\Concerns\AssertsModuleBoundaries;
use LaravelModulesArch\Testing\Concerns\MocksCrossModuleActions;
use LaravelModulesArch\Testing\IsolatedModuleTest;
use Modules\Account\Actions\GetFinancialSummary;
use Modules\Sale\Application\Actions\CompleteSaleOrder;

class SaleModuleIntegrationTest extends IsolatedModuleTest
{
    use MocksCrossModuleActions;
    use AssertsModuleBoundaries;

    protected array $enabledModules = ['Base', 'Sale'];

    public function test_sale_can_complete_an_order_without_account_being_installed(): void
    {
        $this->fakeCrossModule('Account', GetFinancialSummary::class, []);

        $order = app(CompleteSaleOrder::class)->handle('order-1');

        $this->assertSame('completed', $order->status());
    }

    public function test_sale_passes_arch_boundary_rules(): void
    {
        $this->assertModuleHasNoBoundaryErrors('Sale');
    }
}
```

---

## Lower-level helpers

If you need finer control than the abstract class provides, the
underlying helper is public:

```php
use LaravelModulesArch\Testing\ModuleIsolation;
use Nwidart\Modules\Contracts\RepositoryInterface;

class CustomLifecycleTest extends YourTestCase
{
    public function test_with_custom_isolation_window(): void
    {
        /** @var RepositoryInterface $repo */
        $repo = $this->app->make('modules');

        $previousState = ModuleIsolation::apply($repo, ['Sale']);

        try {
            // ... your custom test logic ...
        } finally {
            ModuleIsolation::restore($repo, $previousState);
        }
    }
}
```

`ModuleIsolation::apply()` returns the previous enabled state as
`array<string, bool>`; pass it to `restore()` later to put everything back.
