<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

use PhpParser\Node;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * Extracts every `use` statement (single and grouped) from a PHP source file
 * using {@see ParserFactory}. The parser is the only way to do this
 * reliably — naive regexes break on heredocs, leading whitespace, etc.
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

            public function enterNode(Node $node): null
            {
                if ($node instanceof Use_) {
                    foreach ($node->uses as $use) {
                        $this->imports[] = new ImportStatement(
                            namespace: $use->name->toString(),
                            line: $node->getStartLine(),
                        );
                    }

                    return null;
                }

                if ($node instanceof GroupUse) {
                    $prefix = $node->prefix->toString();
                    foreach ($node->uses as $use) {
                        $this->imports[] = new ImportStatement(
                            namespace: $prefix.'\\'.$use->name->toString(),
                            line: $node->getStartLine(),
                        );
                    }
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->imports;
    }
}
