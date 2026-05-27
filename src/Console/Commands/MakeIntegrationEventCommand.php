<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeIntegrationEventCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-integration-event
                            {name : Module/EventName, e.g. Sale/SaleOrderCompleted}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a public Integration Event POPO and declare it in module.json → events.publishes.';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/contracts/IntegrationEvent.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Contracts/Events/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Integration Event';
    }

    protected function manifestUpdate(string $module, string $name): ?array
    {
        return [
            'events.publishes',
            'Modules\\'.$module.'\\Contracts\\Events\\'.$name,
        ];
    }
}
