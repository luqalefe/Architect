<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeEventCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-event
                            {name : Module/EventName, e.g. Sale/OrderItemAdded}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a Domain Event stub (internal POPO, NOT an Integration Event).';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/domain/Event.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Domain/Events/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Domain Event';
    }
}
