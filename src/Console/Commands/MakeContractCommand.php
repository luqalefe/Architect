<?php

declare(strict_types=1);

namespace LaravelModulesArch\Console\Commands;

class MakeContractCommand extends AbstractMakeArtifactCommand
{
    protected $signature = 'arch:make-contract
                            {name : Module/ContractName, e.g. Sale/SaleOrderContract}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a public Contract interface and declare it in module.json → contracts.publishes.';

    protected function stubPath(): string
    {
        return $this->stubsRoot().'/contracts/Contract.stub';
    }

    protected function outputSubPath(): string
    {
        return 'Contracts/{{ NAME }}.php';
    }

    protected function artifactKind(): string
    {
        return 'Contract';
    }

    protected function manifestUpdate(string $module, string $name): ?array
    {
        return [
            'contracts.publishes',
            'Modules\\'.$module.'\\Contracts\\'.$name,
        ];
    }
}
