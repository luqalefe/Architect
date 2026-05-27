<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Support;

use LaravelModulesArch\Support\StubRenderer;
use LaravelModulesArch\Tests\TestCase;
use RuntimeException;

class StubRendererTest extends TestCase
{
    public function test_replaces_tokens_with_surrounding_spaces(): void
    {
        $rendered = (new StubRenderer)->render(
            'Hello, {{ NAME }}! Mode: {{ MODE }}.',
            ['NAME' => 'Sale', 'MODE' => 'purist'],
        );

        $this->assertSame('Hello, Sale! Mode: purist.', $rendered);
    }

    public function test_replaces_tokens_without_spaces(): void
    {
        $rendered = (new StubRenderer)->render(
            'Namespace: {{NAMESPACE}}',
            ['NAMESPACE' => 'Modules\\Sale'],
        );

        $this->assertSame('Namespace: Modules\\Sale', $rendered);
    }

    public function test_replaces_the_same_token_in_multiple_positions(): void
    {
        $rendered = (new StubRenderer)->render(
            '{{ NAME }} controllers live in {{ NAME }}/Http.',
            ['NAME' => 'Sale'],
        );

        $this->assertSame('Sale controllers live in Sale/Http.', $rendered);
    }

    public function test_leaves_template_untouched_when_no_replacements_match(): void
    {
        $rendered = (new StubRenderer)->render(
            'No placeholders here.',
            ['UNUSED' => 'value'],
        );

        $this->assertSame('No placeholders here.', $rendered);
    }

    public function test_render_file_reads_from_disk_and_substitutes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stub-');
        $this->assertNotFalse($path);
        file_put_contents($path, '<?php // {{ NAME }}');

        try {
            $rendered = (new StubRenderer)->renderFile($path, ['NAME' => 'Sale']);
            $this->assertSame('<?php // Sale', $rendered);
        } finally {
            @unlink($path);
        }
    }

    public function test_render_file_throws_when_path_is_unreadable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stub');

        (new StubRenderer)->renderFile('/definitely/does/not/exist.stub', []);
    }
}
