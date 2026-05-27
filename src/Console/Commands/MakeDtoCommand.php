<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeDtoCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-dto
                            {name : Module/DTOName, e.g. Sale/CreateSaleOrderData}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a final readonly Data Transfer Object stub with fromRequest()/fromArray().';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/application/DTO.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Application/DTOs/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'DTO';
    }
}
