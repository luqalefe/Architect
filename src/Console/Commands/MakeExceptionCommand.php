<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeExceptionCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-exception
                            {name : Module/ExceptionName, e.g. Sale/InvalidOrderTransitionException}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a Domain Exception stub extending \DomainException.';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/domain/Exception.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Domain/Exceptions/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Exception';
    }
}
