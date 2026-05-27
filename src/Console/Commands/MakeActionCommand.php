<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeActionCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-action
                            {name : Module/ActionName, e.g. Sale/CreateSaleOrder}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate an Application Service / Use Case (Action) stub.';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/application/Action.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Application/Actions/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Action';
    }
}
