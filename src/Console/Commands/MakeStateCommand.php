<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeStateCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-state
                            {name : Module/StateName, e.g. Sale/OrderStatus}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a State enum stub with canTransitionTo() and a throwing transitionTo().';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/domain/State.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Domain/Enums/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'State';
    }
}
