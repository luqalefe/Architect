<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests;

use Illuminate\Foundation\Application;
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
}
