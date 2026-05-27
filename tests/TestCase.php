<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use LaravelModulesArch\LaravelModulesArchServiceProvider;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelModulesArchServiceProvider::class,
        ];
    }

    /**
     * Non-nullable accessor for the booted application. Orchestra types
     * $this->app as nullable; this asserts it's been booted so callers get
     * back a concrete Application.
     */
    protected function app(): Application
    {
        if ($this->app === null) {
            throw new RuntimeException('Test application has not been booted.');
        }

        return $this->app;
    }

    /**
     * Binds a Mockery mock as the 'modules' service so the nwidart Module
     * facade resolves through the container without needing its provider.
     *
     * @param  callable(MockInterface): void  $configure
     */
    protected function fakeModules(callable $configure): void
    {
        $mock = Mockery::mock();
        $configure($mock);
        $this->app()->instance('modules', $mock);
    }

    /**
     * Typed wrapper over $this->artisan() — Laravel returns PendingCommand|int
     * and PHPStan can't narrow the union, so this asserts the expected branch
     * (mocked console output) and hands back a PendingCommand.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function artisanPending(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        if (! $result instanceof PendingCommand) {
            throw new RuntimeException('artisan() returned an int. mockConsoleOutput must be enabled for this helper.');
        }

        return $result;
    }
}
