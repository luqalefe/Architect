<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Idempotently appends string entries to a list inside a module.json manifest
 * (e.g. `contracts.publishes`, `events.publishes`, `events.subscribes`).
 *
 * Workflow:
 *   1. Read & decode the manifest.
 *   2. Walk to the dot-path, creating nested objects/arrays as needed.
 *   3. Append the entry only if it isn't already in the list.
 *   4. Validate the result against {@see ModuleJsonSchema}.
 *   5. Rewrite the file pretty-printed (matches what arch:make-module emits).
 *
 * Returns the validation result + a hint about whether the entry was actually
 * added or skipped because it was already present.
 */
final class ManifestUpdater
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleJsonSchema $schema,
    ) {}

    public function addToList(string $manifestPath, string $dotPath, string $entry): ManifestUpdateResult
    {
        if (! $this->files->exists($manifestPath)) {
            throw new RuntimeException('module.json not found at '.$manifestPath);
        }

        $raw = $this->files->get($manifestPath);
        $manifest = json_decode($raw, true);
        if (! is_array($manifest)) {
            throw new RuntimeException('module.json is not valid JSON: '.json_last_error_msg());
        }

        $segments = explode('.', $dotPath);
        $already = $this->listAt($manifest, $segments);

        if (in_array($entry, $already, true)) {
            /** @var array<string, mixed> $manifest */
            return new ManifestUpdateResult(
                added: false,
                validation: $this->schema->validate($manifest),
            );
        }

        $updated = $this->withAppended($manifest, $segments, $entry);

        /** @var array<string, mixed> $updated */
        $validation = $this->schema->validate($updated);

        if (! $validation->isValid()) {
            return new ManifestUpdateResult(added: false, validation: $validation);
        }

        $encoded = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode module.json: '.json_last_error_msg());
        }

        $this->files->put($manifestPath, $encoded."\n");

        return new ManifestUpdateResult(added: true, validation: $validation);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function listAt(array $manifest, array $segments): array
    {
        $cursor = $manifest;
        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return [];
            }
            $cursor = $cursor[$segment];
        }

        if (! is_array($cursor)) {
            return [];
        }

        $strings = [];
        foreach ($cursor as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * Returns a new manifest with $entry appended to the list at $segments,
     * creating any missing intermediate objects.
     *
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private function withAppended(array $manifest, array $segments, string $entry): array
    {
        $dotPath = implode('.', $segments);

        $existing = Arr::get($manifest, $dotPath, []);
        if (! is_array($existing)) {
            $existing = [];
        }
        $existing[] = $entry;

        Arr::set($manifest, $dotPath, $existing);

        return $manifest;
    }
}
