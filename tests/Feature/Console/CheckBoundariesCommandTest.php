<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Tests\TestCase;

class CheckBoundariesCommandTest extends TestCase
{
    private string $modulesPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = sys_get_temp_dir().'/check-boundaries-'.uniqid();
        $this->files->ensureDirectoryExists($this->modulesPath);

        config(['modules-arch.modules_path' => $this->modulesPath]);

        // Reset the boundary config to a known pragmatic baseline.
        config(['modules-arch.boundaries.enabled' => true]);
        config(['modules-arch.boundaries.rules' => [
            'cross_module_via_contracts' => true,
            'domain_no_infrastructure' => true,
            'domain_no_application' => true,
            'application_no_infrastructure' => false,
            'declared_subscriptions' => true,
            'aggregate_root_repositories' => true,
        ]]);
        config(['modules-arch.boundaries.ignored_namespaces' => [
            'Illuminate\\Support\\',
            'Illuminate\\Contracts\\',
        ]]);
    }

    protected function tearDown(): void
    {
        if ($this->files->exists($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    public function test_clean_module_exits_zero_and_reports_no_violations(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $this->artisanPending('arch:check-boundaries')
            ->expectsOutputToContain('No boundary violations found.')
            ->assertExitCode(0);
    }

    public function test_detects_r1_cross_module_violation_and_reports_it(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Crm'])->assertSuccessful();

        $this->files->put(
            $this->modulesPath.'/Sale/Application/Actions/Bad.php',
            "<?php\nnamespace Modules\\Sale\\Application\\Actions;\nuse Modules\\Crm\\Domain\\Entities\\Lead;\nfinal class Bad {}\n",
        );

        $this->artisanPending('arch:check-boundaries')
            ->expectsOutputToContain('[R1]')
            ->assertExitCode(0);
    }

    public function test_strict_flag_fails_when_errors_are_present(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Crm'])->assertSuccessful();

        $this->files->put(
            $this->modulesPath.'/Sale/Application/Actions/Bad.php',
            "<?php\nnamespace Modules\\Sale\\Application\\Actions;\nuse Modules\\Crm\\Domain\\Entities\\Lead;\nfinal class Bad {}\n",
        );

        $this->artisanPending('arch:check-boundaries', ['--strict' => true])->assertExitCode(1);
    }

    public function test_strict_flag_passes_when_only_warnings_are_present(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->files->ensureDirectoryExists($this->modulesPath.'/Sale/Domain/Repositories');
        $this->files->put(
            $this->modulesPath.'/Sale/Domain/Repositories/SaleOrderItemRepositoryInterface.php',
            "<?php\nnamespace Modules\\Sale\\Domain\\Repositories;\ninterface SaleOrderItemRepositoryInterface {}\n",
        );

        $this->artisanPending('arch:check-boundaries', ['--strict' => true])
            ->expectsOutputToContain('[R6]')
            ->assertExitCode(0);
    }

    public function test_module_filter_excludes_violations_in_other_modules(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Crm'])->assertSuccessful();

        // Plant a violation in Crm
        $this->files->put(
            $this->modulesPath.'/Crm/Application/Actions/Bad.php',
            "<?php\nnamespace Modules\\Crm\\Application\\Actions;\nuse Modules\\Sale\\Domain\\Entities\\Order;\nfinal class Bad {}\n",
        );

        // --module=Sale should not see the Crm violation
        $this->artisanPending('arch:check-boundaries', ['--module' => 'Sale', '--strict' => true])->assertExitCode(0);

        // Without filter, --strict catches it
        $this->artisanPending('arch:check-boundaries', ['--strict' => true])->assertExitCode(1);
    }

    public function test_disabled_config_short_circuits_with_info_message(): void
    {
        config(['modules-arch.boundaries.enabled' => false]);

        $this->artisanPending('arch:check-boundaries', ['--strict' => true])
            ->expectsOutputToContain('Boundary enforcement is disabled')
            ->assertExitCode(0);
    }

    public function test_ignored_namespaces_allow_illuminate_support_in_domain(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->files->ensureDirectoryExists($this->modulesPath.'/Sale/Domain/ValueObjects');
        $this->files->put(
            $this->modulesPath.'/Sale/Domain/ValueObjects/OrderTotal.php',
            "<?php\nnamespace Modules\\Sale\\Domain\\ValueObjects;\nuse Illuminate\\Support\\Str;\nfinal readonly class OrderTotal {}\n",
        );

        // Default config includes Illuminate\Support\ in ignored list
        $this->artisanPending('arch:check-boundaries', ['--strict' => true])->assertExitCode(0);

        // Remove the ignore and now it fails
        config(['modules-arch.boundaries.ignored_namespaces' => []]);
        $this->artisanPending('arch:check-boundaries', ['--strict' => true])->assertExitCode(1);
    }

    public function test_purist_mode_auto_enables_r4_even_when_config_says_false(): void
    {
        config(['modules-arch.default_mode' => 'purist']);
        // application_no_infrastructure stays false (the broken-by-default value);
        // the resolver must flip it on because we're in purist.

        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $this->files->ensureDirectoryExists($this->modulesPath.'/Sale/Application/Actions');
        $this->files->put(
            $this->modulesPath.'/Sale/Application/Actions/UsesInfra.php',
            "<?php\nnamespace Modules\\Sale\\Application\\Actions;\nuse Modules\\Sale\\Infrastructure\\Models\\Order;\nfinal class UsesInfra {}\n",
        );

        $this->artisanPending('arch:check-boundaries', ['--strict' => true])
            ->expectsOutputToContain('[R4]')
            ->assertExitCode(1);
    }

    public function test_detects_r3_via_inline_fully_qualified_class_name(): void
    {
        // Without FQCN coverage, this Domain→Application import via inline FQCN
        // would silently slip past boundary enforcement.
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();

        $this->files->ensureDirectoryExists($this->modulesPath.'/Sale/Domain/Services');
        $this->files->put(
            $this->modulesPath.'/Sale/Domain/Services/CheatingService.php',
            "<?php\nnamespace Modules\\Sale\\Domain\\Services;\nfinal class CheatingService {\n    public function run(): void { new \\Modules\\Sale\\Application\\Actions\\DoIt(); }\n}\n",
        );

        $this->artisanPending('arch:check-boundaries', ['--strict' => true])
            ->expectsOutputToContain('[R3]')
            ->assertExitCode(1);
    }

    public function test_detects_r5_when_listener_subscribes_to_undeclared_event(): void
    {
        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Crm'])->assertSuccessful();

        // Plant a listener that imports an event NOT declared in subscribes
        $this->files->ensureDirectoryExists($this->modulesPath.'/Sale/Infrastructure/ACL');
        $this->files->put(
            $this->modulesPath.'/Sale/Infrastructure/ACL/HandleLeadConverted.php',
            "<?php\nnamespace Modules\\Sale\\Infrastructure\\ACL;\nuse Modules\\Crm\\Contracts\\Events\\LeadConverted;\nfinal class HandleLeadConverted {}\n",
        );

        $this->artisanPending('arch:check-boundaries', ['--strict' => true])
            ->expectsOutputToContain('[R5]')
            ->assertExitCode(1);
    }
}
