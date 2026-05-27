<?php

declare(strict_types=1);

namespace LaravelModulesArch;

use Illuminate\Support\ServiceProvider;
use LaravelModulesArch\Console\Commands\MakeActionCommand;
use LaravelModulesArch\Console\Commands\MakeDtoCommand;
use LaravelModulesArch\Console\Commands\MakeEntityCommand;
use LaravelModulesArch\Console\Commands\MakeEnumCommand;
use LaravelModulesArch\Console\Commands\MakeEventCommand;
use LaravelModulesArch\Console\Commands\MakeExceptionCommand;
use LaravelModulesArch\Console\Commands\MakeModuleCommand;
use LaravelModulesArch\Console\Commands\MakeRepositoryCommand;
use LaravelModulesArch\Console\Commands\MakeStateCommand;
use LaravelModulesArch\Console\Commands\MakeValidatorCommand;
use LaravelModulesArch\Console\Commands\MakeValueObjectCommand;
use LaravelModulesArch\Console\Commands\MakeViewModelCommand;

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
            MakeEntityCommand::class,
            MakeValueObjectCommand::class,
            MakeEnumCommand::class,
            MakeEventCommand::class,
            MakeExceptionCommand::class,
            MakeRepositoryCommand::class,
            MakeActionCommand::class,
            MakeDtoCommand::class,
            MakeViewModelCommand::class,
            MakeStateCommand::class,
            MakeValidatorCommand::class,
        ];
    }
}
