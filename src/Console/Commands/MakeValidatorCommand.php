<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeValidatorCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-validator
                            {name : Module/ValidatorName (the "Rules" suffix is added automatically), e.g. Sale/CreateSaleOrder}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate an application-level validation rules class (static rules() method).';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/application/Validator.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Application/Validators/{{ NAME }}Rules.php';
    }

    protected function artifactKind(): string
    {
        return 'Validator';
    }
}
