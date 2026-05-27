<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

/**
 * One scanned `.php` file inside a module, with everything the rules need to
 * make a verdict (import list, layer, owning module).
 */
final readonly class ModuleFile
{
    /**
     * @param  list<ImportStatement>  $imports
     */
    public function __construct(
        public string $path,
        public string $module,
        public string $relativePath,
        public string $layer,
        public array $imports,
    ) {}
}
