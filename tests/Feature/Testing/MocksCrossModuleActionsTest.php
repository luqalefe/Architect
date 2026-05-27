<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Testing;

use LaravelModulesArch\Support\CrossModuleAction;
use LaravelModulesArch\Testing\Concerns\MocksCrossModuleActions;
use LaravelModulesArch\Tests\TestCase;

class MocksCrossModuleActionsTest extends TestCase
{
    use MocksCrossModuleActions;

    public function test_fake_cross_module_returns_the_stub_value(): void
    {
        $this->fakeCrossModule('Account', FakeForCrossModuleTest::class, ['summary' => 'ok']);

        $result = CrossModuleAction::run('Account', FakeForCrossModuleTest::class, ['ignored']);

        $this->assertSame(['summary' => 'ok'], $result);
    }

    public function test_fake_cross_module_treats_target_module_as_enabled(): void
    {
        // No real Module facade setup; fakeCrossModule must arrange it.
        $this->fakeCrossModule('Account', FakeForCrossModuleTest::class, 42);

        $result = CrossModuleAction::run(
            'Account',
            FakeForCrossModuleTest::class,
            [],
            default: 'fallback-should-not-be-used',
        );

        $this->assertSame(42, $result);
    }

    public function test_multiple_fakes_coexist_per_action_class(): void
    {
        $this->fakeCrossModule('Account', FakeForCrossModuleTest::class, 'first');
        $this->fakeCrossModule('Crm', OtherFakeForCrossModuleTest::class, 'second');

        $this->assertSame('first', CrossModuleAction::run('Account', FakeForCrossModuleTest::class));
        $this->assertSame('second', CrossModuleAction::run('Crm', OtherFakeForCrossModuleTest::class));
    }

    public function test_real_action_is_called_when_no_fake_is_registered(): void
    {
        // Without fakeCrossModule, Module::isEnabled blows up under our test
        // (no 'modules' binding) — CrossModuleAction catches the throw and
        // returns the default. Confirms the fake-helper is what changes the
        // behaviour, not some implicit binding leakage.
        $result = CrossModuleAction::run('Account', FakeForCrossModuleTest::class, [], default: 'real-default');

        $this->assertSame('real-default', $result);
    }
}

/**
 * @internal Real (autoloadable) action class so CrossModuleAction's
 * class_exists() guard passes; the helper substitutes its implementation.
 */
class FakeForCrossModuleTest
{
    public function handle(mixed ...$args): mixed
    {
        return 'real-implementation-not-substituted';
    }
}

/**
 * @internal Second real action class, used by the multi-fake test.
 */
class OtherFakeForCrossModuleTest
{
    public function handle(mixed ...$args): mixed
    {
        return 'real-implementation-not-substituted';
    }
}
