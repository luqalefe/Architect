<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeEnumCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-enum
                            {name : Module/EnumName, e.g. Sale/OrderStatus}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a backed Enum stub with canTransitionTo().';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/domain/Enum.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Domain/Enums/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Enum';
    }
}
