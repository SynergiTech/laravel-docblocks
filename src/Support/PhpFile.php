<?php

namespace SynergiTech\LaravelDocblocks\Support;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

final class PhpFile
{
    /**
     * @param array<int, \PhpParser\Node\Stmt> $oldStatements
     * @param array<int, \PhpParser\Node\Stmt> $newStatements
     * @param array<int, \PhpParser\Token> $tokens
     */
    private function __construct(
        private readonly string $path,
        private readonly string $code,
        private readonly array $oldStatements,
        private readonly array $newStatements,
        private readonly array $tokens,
    ) {
    }

    public static function fromPath(string $path): self
    {
        $code = file_get_contents($path);

        if ($code === false) {
            throw new RuntimeException("Could not read file: {$path}");
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $oldStatements = $parser->parse($code);

        if ($oldStatements === null) {
            throw new RuntimeException("Could not parse PHP file: {$path}");
        }

        /** @var array<int, \PhpParser\Node\Stmt> $newStatements */
        $newStatements = (new NodeTraverser(
            new CloningVisitor(),
        ))->traverse($oldStatements);

        return new self(
            $path,
            $code,
            $oldStatements,
            $newStatements,
            $parser->getTokens(),
        );
    }

    /**
     * @return array<string, Class_>
     */
    public function classes(): array
    {
        return $this->classesIn($this->newStatements);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function render(): string
    {
        return (new Standard())->printFormatPreserving(
            $this->newStatements,
            $this->oldStatements,
            $this->tokens,
        );
    }

    /**
     * @param array<int, \PhpParser\Node\Stmt> $statements
     * @return array<string, Class_>
     */
    private function classesIn(array $statements): array
    {
        $classes = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Namespace_) {
                $namespace = $statement->name?->toString() ?? '';

                foreach ($statement->stmts ?? [] as $namespaceStatement) {
                    $this->addClass($classes, $namespace, $namespaceStatement);
                }

                continue;
            }

            $this->addClass($classes, '', $statement);
        }

        return $classes;
    }

    /**
     * @param array<string, Class_> $classes
     */
    private function addClass(array &$classes, string $namespace, object $statement): void
    {
        if (! $statement instanceof Class_ || $statement->name === null) {
            return;
        }

        $classes[ltrim($namespace . '\\' . $statement->name->toString(), '\\')] = $statement;
    }
}
