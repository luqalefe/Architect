<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Support;

use LaravelModulesArch\Support\CrossModuleAction;
use LaravelModulesArch\Tests\TestCase;
use Mockery\MockInterface;
use RuntimeException;

class CrossModuleActionTest extends TestCase
{
    public function test_returns_default_when_module_is_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(false));

        $result = CrossModuleAction::run('Account', CrossModuleFakeAction::class, default: ['fallback']);

        $this->assertSame(['fallback'], $result);
    }

    public function test_returns_default_when_action_class_does_not_exist(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(true));

        $result = CrossModuleAction::run('Account', 'Modules\\Account\\NotARealClass', default: 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function test_returns_default_when_module_facade_throws(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')
            ->with('Ghost')
            ->andThrow(new RuntimeException('Module [Ghost] does not exist.')));

        $result = CrossModuleAction::run('Ghost', CrossModuleFakeAction::class, default: 'safe');

        $this->assertSame('safe', $result);
    }

    public function test_invokes_action_handle_with_positional_params(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(true));

        $result = CrossModuleAction::run('Account', CrossModuleFakeAction::class, ['hello', 3]);

        $this->assertSame('handled:hello:3', $result);
    }

    public function test_resolves_action_through_the_container(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(true));

        $this->app()->instance(CrossModuleFakeAction::class, new class
        {
            public function handle(): string
            {
                return 'from-container';
            }
        });

        $result = CrossModuleAction::run('Account', CrossModuleFakeAction::class);

        $this->assertSame('from-container', $result);
    }

    public function test_default_can_be_a_closure_that_is_lazily_evaluated(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(false));

        $called = false;
        $result = CrossModuleAction::run('Account', CrossModuleFakeAction::class, default: function () use (&$called) {
            $called = true;

            return 'lazy';
        });

        $this->assertTrue($called, 'Closure default should be invoked when fallback path is taken.');
        $this->assertSame('lazy', $result);
    }
}

/**
 * @internal Test fixture Action.
 */
class CrossModuleFakeAction
{
    public function handle(string $msg, int $times): string
    {
        return 'handled:'.$msg.':'.$times;
    }
}
