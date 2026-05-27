<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeViewModelCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-view-model
                            {name : Module/ViewModelName, e.g. Sale/SaleOrderIndexViewModel}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a ViewModel stub that exposes view-facing methods (not raw properties).';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/application/ViewModel.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Application/ViewModels/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'ViewModel';
    }
}
