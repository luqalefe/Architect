<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

use RuntimeException;

/**
 * Minimal text-template renderer for stub files.
 *
 * Replaces tokens shaped `{{ KEY }}` (with or without surrounding spaces) using
 * the values in the supplied map. Used by stub-based generators across the
 * package — file contents and even file-path segments may contain tokens.
 */
final class StubRenderer
{
    /**
     * @param  array<string, string>  $replacements
     */
    public function render(string $template, array $replacements): string
    {
        $search = [];
        $replace = [];

        foreach ($replacements as $key => $value) {
            $search[] = '{{ '.$key.' }}';
            $search[] = '{{'.$key.'}}';
            $replace[] = $value;
            $replace[] = $value;
        }

        return str_replace($search, $replace, $template);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function renderFile(string $path, array $replacements): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Unable to read stub at '.$path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stub at '.$path);
        }

        return $this->render($contents, $replacements);
    }
}
