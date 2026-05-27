<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Unit\Analysis;

use Illuminate\Filesystem\Filesystem;
use LaravelModulesArch\Analysis\ImportExtractor;
use LaravelModulesArch\Tests\TestCase;
use RuntimeException;

class ImportExtractorTest extends TestCase
{
    private string $tempPath;

    private Filesystem $files;

    private ImportExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tempPath = sys_get_temp_dir().'/import-extractor-'.uniqid();
        $this->files->ensureDirectoryExists($this->tempPath);
        $this->extractor = new ImportExtractor;
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tempPath);
        parent::tearDown();
    }

    public function test_extracts_single_use_statements(): void
    {
        $file = $this->writeFile('Foo.php', <<<'PHP'
            <?php

            namespace Acme;

            use Foo\Bar;
            use Foo\Baz;

            class Foo {}
            PHP);

        $imports = $this->extractor->extract($file);

        $this->assertCount(2, $imports);
        $this->assertSame('Foo\\Bar', $imports[0]->namespace);
        $this->assertSame('Foo\\Baz', $imports[1]->namespace);
    }

    public function test_extracts_grouped_use_statements(): void
    {
        $file = $this->writeFile('Foo.php', <<<'PHP'
            <?php

            namespace Acme;

            use Foo\{Bar, Baz, Qux};
            PHP);

        $imports = $this->extractor->extract($file);

        $this->assertCount(3, $imports);
        $namespaces = array_map(fn ($i) => $i->namespace, $imports);
        $this->assertContains('Foo\\Bar', $namespaces);
        $this->assertContains('Foo\\Baz', $namespaces);
        $this->assertContains('Foo\\Qux', $namespaces);
    }

    public function test_records_line_numbers(): void
    {
        $file = $this->writeFile('Foo.php', <<<'PHP'
            <?php

            namespace Acme;

            use Foo\Bar;
            PHP);

        $imports = $this->extractor->extract($file);

        $this->assertCount(1, $imports);
        $this->assertSame(5, $imports[0]->line);
    }

    public function test_returns_empty_list_for_file_without_imports(): void
    {
        $file = $this->writeFile('Foo.php', <<<'PHP'
            <?php

            namespace Acme;

            class Foo {}
            PHP);

        $imports = $this->extractor->extract($file);

        $this->assertSame([], $imports);
    }

    public function test_throws_when_file_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);

        $this->extractor->extract('/no/such/file.php');
    }

    public function test_extracts_inline_fully_qualified_class_names(): void
    {
        $file = $this->writeFile('Foo.php', <<<'PHP'
            <?php

            namespace Modules\Sale\Application\Actions;

            class CreateOrder
            {
                public function run(): void
                {
                    $lead = new \Modules\Crm\Domain\Entities\Lead();
                    \Modules\Crm\Infrastructure\Models\Repo::find(1);
                }
            }
            PHP);

        $namespaces = array_map(fn ($i) => $i->namespace, $this->extractor->extract($file));

        $this->assertContains('Modules\\Crm\\Domain\\Entities\\Lead', $namespaces);
        $this->assertContains('Modules\\Crm\\Infrastructure\\Models\\Repo', $namespaces);
    }

    public function test_dedupes_inline_fqcn_against_use_statement(): void
    {
        $file = $this->writeFile('Foo.php', <<<'PHP'
            <?php

            namespace Acme;

            use Modules\Crm\Contracts\LeadService;

            class Foo
            {
                public function run(): void
                {
                    \Modules\Crm\Contracts\LeadService::make();
                    \Modules\Crm\Contracts\LeadService::make();
                }
            }
            PHP);

        $namespaces = array_map(fn ($i) => $i->namespace, $this->extractor->extract($file));

        $matches = array_filter($namespaces, fn ($n) => $n === 'Modules\\Crm\\Contracts\\LeadService');
        $this->assertCount(1, $matches);
    }

    private function writeFile(string $name, string $contents): string
    {
        $path = $this->tempPath.'/'.$name;
        $this->files->put($path, $contents);

        return $path;
    }
}
