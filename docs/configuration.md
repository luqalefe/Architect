# Configuration Reference

After `composer require luq/laravel-modules-arch`, publish the config with:

```bash
php artisan vendor:publish --tag=modules-arch-config
```

This drops `config/modules-arch.php` into your app. The full annotated default
is reproduced below, followed by per-key reference.

## The full config

```php
return [
    'modules_path' => base_path('Modules'),

    'default_mode' => env('MODULES_ARCH_MODE', 'pragmatic'),

    'boundaries' => [
        'enabled' => true,

        'rules' => [
            'cross_module_via_contracts'    => true,  // R1
            'domain_no_infrastructure'      => true,  // R2
            'domain_no_application'         => true,  // R3
            'application_no_infrastructure' => false, // R4 (purist only)
            'declared_subscriptions'        => true,  // R5
            'aggregate_root_repositories'   => true,  // R6 (warning, not error)
        ],

        'ignored_namespaces' => [
            'Illuminate\\Support\\',
            'Illuminate\\Contracts\\',
        ],
    ],
];
```

---

## `modules_path`

**Type:** `string`
**Default:** `base_path('Modules')`

Absolute path where modules live. Mirrors the `nwidart/laravel-modules`
default, so existing nwidart projects don't need to override.

`arch:make-module` writes new modules here. `arch:check-boundaries` scans
this directory. Tests can swap this to a tempdir to keep production modules
untouched (see `tests/Feature/Console/MakeModuleCommandTest.php`).

---

## `default_mode`

**Type:** `'pragmatic' | 'purist'`
**Default:** `'pragmatic'` (overridable via `MODULES_ARCH_MODE` env var)

Controls what `arch:make-module` generates AND which boundary rules are
turned on by default.

| Mode | Generated folders | Rule R4 | Use when |
|---|---|---|---|
| `pragmatic` | Skips `Domain/Entities`, `Domain/ValueObjects`, `Domain/Repositories`, `Infrastructure/Persistence/Repositories` | off | The Eloquent model doubles as the rich entity (Spatie/Beyond CRUD style). Default for most apps. |
| `purist` | Generates everything | on | Hard separation between domain and ORM; Application only depends on Domain interfaces. |

You can graduate from pragmatic to purist later: just run
`arch:make-entity`, `arch:make-value-object`, `arch:make-repository` for the
domains that need it.

---

## `boundaries.enabled`

**Type:** `bool`
**Default:** `true`

Master switch. When `false`, `arch:check-boundaries` short-circuits with an
info message and exits 0 — useful for spike branches where you want to
postpone enforcement.

---

## `boundaries.rules`

**Type:** `array<string, bool>`

Each rule maps to a class under `LaravelModulesArch\Analysis\Rules\` and can
be turned on or off individually.

| Key | Rule class | Severity | Pragmatic default | Purist default |
|---|---|---|---|---|
| `cross_module_via_contracts` | `CrossModuleOnlyViaContractsRule` | error | on | on |
| `domain_no_infrastructure` | `DomainCannotImportInfrastructureRule` | error | on | on |
| `domain_no_application` | `DomainCannotImportApplicationRule` | error | on | on |
| `application_no_infrastructure` | `ApplicationCannotImportInfrastructureRule` | error | **off** | on |
| `declared_subscriptions` | `DeclaredSubscriptionsRule` | error | on | on |
| `aggregate_root_repositories` | `AggregateRootRepositoriesRule` | warning | on | on |

See [docs/rules.md](./rules.md) for what each rule actually checks (with
valid/invalid examples).

---

## `boundaries.ignored_namespaces`

**Type:** `list<string>`
**Default:** `['Illuminate\\Support\\', 'Illuminate\\Contracts\\']`

Namespaces that the **R2** rule (`domain_no_infrastructure`) treats as
framework-safe. By default `Illuminate\Support\` (Str, Collection, Arr,
Carbon, etc.) and `Illuminate\Contracts\` (interface-only) are allowed —
they're widely used as framework-agnostic helpers in practice.

Add your own allowlist entries here, with a trailing backslash:

```php
'ignored_namespaces' => [
    'Illuminate\\Support\\',
    'Illuminate\\Contracts\\',
    'Ramsey\\Uuid\\',
    'Carbon\\',
],
```

> Be conservative — every entry here is a hole in the Domain's purity. Prefer
> wrapping framework types behind your own Domain interfaces when possible.

---

## Recipe: turning on purist mode

```diff
- 'default_mode' => 'pragmatic',
+ 'default_mode' => 'purist',
  // ...
  'rules' => [
      // ...
-     'application_no_infrastructure' => false, // R4 (purist only)
+     'application_no_infrastructure' => true,  // R4 on
  ],
```

Then `arch:make-module` will generate the full Domain layout, and
`arch:check-boundaries` will flag any Application file importing
Infrastructure.

## Recipe: per-environment relaxation

The `MODULES_ARCH_MODE` env var lets you keep pragmatic locally and purist in CI:

```bash
# .env
MODULES_ARCH_MODE=pragmatic

# CI
MODULES_ARCH_MODE=purist
```

The Artisan generators consult the env at generate time, so this only
affects modules created from then on — it does NOT retroactively delete
folders from existing modules.
