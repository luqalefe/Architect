<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeValueObjectCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-value-object
                            {name : Module/ValueObjectName, e.g. Sale/OrderTotal}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate an immutable Value Object stub.';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/domain/ValueObject.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Domain/ValueObjects/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Value Object';
    }
}
