<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeEntityCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-entity
                            {name : Module/EntityName, e.g. Sale/SaleOrder}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a Domain Entity stub.';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/domain/Entity.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Domain/Entities/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Entity';
    }
}
