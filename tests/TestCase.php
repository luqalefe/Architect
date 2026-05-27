<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests;

use Illuminate\Foundation\Application;
use LaravelModulesArch\LaravelModulesArchServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

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
}
