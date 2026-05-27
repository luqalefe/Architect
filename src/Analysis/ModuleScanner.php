<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Walks the modules root and emits a {@see ModuleFile} for every `.php` file
 * inside `Modules/{X}/...`, with its layer pre-classified.
 */
final class ModuleScanner
{
    private const KNOWN_LAYERS = ['Domain', 'Application', 'Infrastructure', 'Contracts'];

    public function __construct(
        private readonly Filesystem $files,
        private readonly ImportExtractor $extractor,
    ) {}

    /**
     * @return list<ModuleFile>
     */
    public function scan(string $modulesPath, ?string $onlyModule = null): array
    {
        if (! $this->files->isDirectory($modulesPath)) {
            return [];
        }

        $modules = $onlyModule !== null
            ? [$modulesPath.'/'.$onlyModule]
            : array_filter(
                $this->files->directories($modulesPath),
                fn (string $dir): bool => is_dir($dir),
            );

        $results = [];

        foreach ($modules as $modulePath) {
            if (! is_dir($modulePath)) {
                continue;
            }

            $moduleName = basename($modulePath);

            $finder = (new Finder)->files()->in($modulePath)->name('*.php');

            foreach ($finder as $fileInfo) {
                $absolute = $fileInfo->getRealPath();
                if ($absolute === false) {
                    continue;
                }

                $relative = str_replace(DIRECTORY_SEPARATOR, '/', $fileInfo->getRelativePathname());

                $results[] = new ModuleFile(
                    path: $absolute,
                    module: $moduleName,
                    relativePath: $relative,
                    layer: $this->detectLayer($relative),
                    imports: $this->extractor->extract($absolute),
                );
            }
        }

        return $results;
    }

    private function detectLayer(string $relativePath): string
    {
        $first = explode('/', $relativePath)[0];

        return in_array($first, self::KNOWN_LAYERS, true) ? $first : 'Other';
    }
}
