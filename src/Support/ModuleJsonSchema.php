<?php

declare(strict_types=1);

namespace LaravelModulesArch\Support;

use JsonSchema\Validator;
use RuntimeException;

/**
 * Validates a parsed `module.json` payload against the package's JSON Schema.
 *
 * The schema lives at {@see self::schemaPath()} and intentionally allows
 * additional root-level properties so nwidart-specific keys (e.g. `files`,
 * `keywords`) survive a round-trip through this validator.
 */
final class ModuleJsonSchema
{
    /**
     * @param  array<string, mixed>  $data  Decoded module.json contents.
     */
    public function validate(array $data): ValidationResult
    {
        $validator = new Validator;
        $payload = $this->arrayToObject($data);
        $schema = $this->loadSchema();

        $validator->validate($payload, $schema);

        if ($validator->isValid()) {
            return new ValidationResult(valid: true);
        }

        $errors = array_map(
            static fn (array $error): string => self::formatError($error),
            $validator->getErrors(),
        );

        return new ValidationResult(valid: false, errors: array_values($errors));
    }

    public static function schemaPath(): string
    {
        return dirname(__DIR__, 2).'/resources/schemas/module.schema.json';
    }

    private function loadSchema(): object
    {
        $contents = file_get_contents(self::schemaPath());
        if ($contents === false) {
            throw new RuntimeException('Unable to read module.json schema at '.self::schemaPath());
        }

        $decoded = json_decode($contents);
        if (! is_object($decoded)) {
            throw new RuntimeException('module.json schema is not a valid JSON object.');
        }

        return $decoded;
    }

    /**
     * justinrainbow/json-schema validates against stdClass trees, not arrays.
     *
     * @param  array<string, mixed>  $data
     */
    private function arrayToObject(array $data): object
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new RuntimeException('Failed to encode module.json data: '.json_last_error_msg());
        }

        $decoded = json_decode($json);
        if (! is_object($decoded)) {
            throw new RuntimeException('Decoded module.json payload is not an object.');
        }

        return $decoded;
    }

    /**
     * @param  array{property?: string, pointer?: string, message?: string}  $error
     */
    private static function formatError(array $error): string
    {
        $location = $error['property'] ?? $error['pointer'] ?? '(root)';
        $message = $error['message'] ?? 'unknown validation error';

        return ($location === '' ? '(root)' : $location).': '.$message;
    }
}
