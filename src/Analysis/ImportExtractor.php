<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * Extracts every namespace reference from a PHP source file: `use` statements
 * (single and grouped) AND inline fully-qualified class names like
 * `new \Modules\Crm\Domain\Entities\Lead()`. Without picking up the inline form,
 * the boundary rules could be trivially bypassed by skipping the import.
 *
 * Uses {@see ParserFactory}; regex would break on heredocs, attributes, etc.
 */
final class ImportExtractor
{
    /**
     * @return list<ImportStatement>
     */
    public function extract(string $filePath): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('Cannot read file: '.$filePath);
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException('Failed to read file: '.$filePath);
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        if ($ast === null) {
            return [];
        }

        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var list<ImportStatement> */
            public array $imports = [];

            /** @var array<string, true> Dedup index keyed by namespace string. */
            private array $seen = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Use_) {
                    foreach ($node->uses as $use) {
                        $this->record($use->name->toString(), $node->getStartLine());
                    }

                    return null;
                }

                if ($node instanceof GroupUse) {
                    $prefix = $node->prefix->toString();
                    foreach ($node->uses as $use) {
                        $this->record($prefix.'\\'.$use->name->toString(), $node->getStartLine());
                    }

                    return null;
                }

                // Inline fully-qualified names anywhere in code (instantiation,
                // static call, type hint, attribute, etc.). PhpParser already
                // tagged these as FullyQualified, so no resolver pass needed.
                if ($node instanceof FullyQualified) {
                    $this->record($node->toString(), $node->getStartLine());
                }

                return null;
            }

            private function record(string $namespace, int $line): void
            {
                if (isset($this->seen[$namespace])) {
                    return;
                }

                $this->seen[$namespace] = true;
                $this->imports[] = new ImportStatement(namespace: $namespace, line: $line);
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->imports;
    }
}
