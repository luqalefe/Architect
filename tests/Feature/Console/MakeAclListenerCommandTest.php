<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Tests\TestCase;

class MakeAclListenerCommandTest extends TestCase
{
    private string $modulesPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->modulesPath = sys_get_temp_dir().'/modules-arch-'.uniqid();
        $this->files->ensureDirectoryExists($this->modulesPath);

        config(['modules-arch.modules_path' => $this->modulesPath]);

        $this->artisanPending('arch:make-module', ['name' => 'Sale'])->assertSuccessful();
        $this->artisanPending('arch:make-module', ['name' => 'Crm'])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        if ($this->files->exists($this->modulesPath)) {
            $this->files->deleteDirectory($this->modulesPath);
        }

        parent::tearDown();
    }

    public function test_creates_listener_at_expected_acl_path(): void
    {
        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
        ])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Infrastructure/ACL/HandleLeadConverted.php');
    }

    public function test_listener_imports_event_and_types_handle_parameter(): void
    {
        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
        ])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Infrastructure/ACL/HandleLeadConverted.php');
        $this->assertStringContainsString('namespace Modules\\Sale\\Infrastructure\\ACL;', $contents);
        $this->assertStringContainsString('use Modules\\Crm\\Contracts\\Events\\LeadConverted;', $contents);
        $this->assertStringContainsString('final class HandleLeadConverted', $contents);
        $this->assertStringContainsString('public function handle(LeadConverted $event): void', $contents);
    }

    public function test_sale_module_json_lists_event_in_subscribes(): void
    {
        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
        ])->assertSuccessful();

        $manifest = json_decode($this->files->get($this->modulesPath.'/Sale/module.json'), true);
        $this->assertContains(
            'Modules\\Crm\\Contracts\\Events\\LeadConverted',
            $manifest['events']['subscribes'],
        );
    }

    public function test_sale_service_provider_has_event_listen_between_markers(): void
    {
        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
        ])->assertSuccessful();

        $contents = $this->files->get($this->modulesPath.'/Sale/Infrastructure/Providers/SaleServiceProvider.php');

        $this->assertStringContainsString('\\Illuminate\\Support\\Facades\\Event::listen(', $contents);
        $this->assertStringContainsString('\\Modules\\Crm\\Contracts\\Events\\LeadConverted::class,', $contents);
        $this->assertStringContainsString('\\Modules\\Sale\\Infrastructure\\ACL\\HandleLeadConverted::class,', $contents);

        $listenPos = strpos($contents, 'Event::listen(');
        $markerPos = strpos($contents, '// @arch-listeners-end');
        $this->assertIsInt($listenPos);
        $this->assertIsInt($markerPos);
        $this->assertLessThan($markerPos, $listenPos);
    }

    public function test_re_running_with_force_does_not_duplicate_listener_registration_nor_manifest(): void
    {
        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
        ])->assertSuccessful();

        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
            '--force' => true,
        ])->assertSuccessful();

        $manifest = json_decode($this->files->get($this->modulesPath.'/Sale/module.json'), true);
        $this->assertIsArray($manifest);
        $this->assertIsArray($manifest['events']);
        $this->assertIsArray($manifest['events']['subscribes']);

        $matches = 0;
        foreach ($manifest['events']['subscribes'] as $entry) {
            if ($entry === 'Modules\\Crm\\Contracts\\Events\\LeadConverted') {
                $matches++;
            }
        }
        $this->assertSame(1, $matches);

        $contents = $this->files->get($this->modulesPath.'/Sale/Infrastructure/Providers/SaleServiceProvider.php');
        $this->assertSame(1, substr_count($contents, 'HandleLeadConverted::class'));
    }

    public function test_rejects_when_for_points_to_a_domain_event(): void
    {
        // Plant a Domain Event in Crm (we don't have arch:make-event for cross-module use here,
        // but the same enforcement applies to anything under Domain/Events/).
        $this->files->ensureDirectoryExists($this->modulesPath.'/Crm/Domain/Events');
        $this->files->put($this->modulesPath.'/Crm/Domain/Events/LeadConverted.php', '<?php // domain event');

        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->modulesPath.'/Sale/Infrastructure/ACL/HandleLeadConverted.php');
    }

    public function test_proceeds_when_domain_and_contract_events_share_a_name(): void
    {
        // Crm publishes an Integration Event named LeadConverted; internally, its
        // Domain Event is also called LeadConverted (Sale/SaleOrderCompleted is the
        // canonical example of this pattern). The same name in Domain/Events/ must
        // not block another module from subscribing to the Contracts/Events/ one.
        $this->files->ensureDirectoryExists($this->modulesPath.'/Crm/Domain/Events');
        $this->files->put($this->modulesPath.'/Crm/Domain/Events/LeadConverted.php', '<?php // domain event');
        $this->files->ensureDirectoryExists($this->modulesPath.'/Crm/Contracts/Events');
        $this->files->put($this->modulesPath.'/Crm/Contracts/Events/LeadConverted.php', '<?php // integration event');

        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'Crm/LeadConverted',
        ])->assertSuccessful();

        $this->assertFileExists($this->modulesPath.'/Sale/Infrastructure/ACL/HandleLeadConverted.php');
    }

    public function test_fails_without_for_option(): void
    {
        $this->artisanPending('arch:make-acl-listener', ['name' => 'Sale/HandleLeadConverted'])->assertFailed();
    }

    public function test_fails_when_for_is_not_module_slash_name(): void
    {
        $this->artisanPending('arch:make-acl-listener', [
            'name' => 'Sale/HandleLeadConverted',
            '--for' => 'JustALeadConverted',
        ])->assertFailed();
    }
}
