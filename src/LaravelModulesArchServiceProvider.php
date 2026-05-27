<?php

declare(strict_types=1);

namespace LaravelModulesArch;

use Illuminate\Support\ServiceProvider;
use LaravelModulesArch\Console\Commands\MakeModuleCommand;

class LaravelModulesArchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/modules-arch.php',
            'modules-arch',
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/modules-arch.php' => config_path('modules-arch.php'),
            ], 'modules-arch-config');

            $this->commands($this->consoleCommands());
        }
    }

    /**
     * @return array<int, class-string>
     */
    protected function consoleCommands(): array
    {
        return [
            MakeModuleCommand::class,
        ];
    }
}
