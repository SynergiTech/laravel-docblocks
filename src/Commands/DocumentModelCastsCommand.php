<?php

namespace SynergiTech\LaravelDocblocks\Commands;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use SynergiTech\LaravelDocblocks\Support\Docblock;

final class DocumentModelCastsCommand extends AbstractDocumentCommand
{
    protected $signature = 'laravel-docblocks:model-casts
                            {--dry-run : Show which models would be updated without modifying files}';

    protected $description = 'Add array shape PHPDoc return types to Eloquent model casts() methods';

    /**
     * @param ReflectionClass<Model> $reflection
     */
    protected function document(ReflectionClass $reflection, Class_ $modelClass): bool
    {
        if (! $reflection->hasMethod('casts')) {
            return false;
        }

        $castsMethod = $reflection->getMethod('casts');

        if ($castsMethod->getDeclaringClass()->getName() !== $reflection->getName()) {
            return false;
        }

        $method = $this->method($modelClass, 'casts');

        if ($method === null) {
            return false;
        }

        /** @var Model $model */
        $model = $reflection->newInstanceWithoutConstructor();
        $docblock = Docblock::casts($method->getDocComment(), $this->casts($model, $castsMethod));

        if ($method->getDocComment()?->getText() === $docblock) {
            return false;
        }

        $method->setDocComment(new Doc($docblock));

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function casts(Model $model, ReflectionMethod $method): array
    {
        $method->setAccessible(true);
        $casts = $method->invoke($model);

        if (! is_array($casts)) {
            throw new RuntimeException('casts() did not return an array.');
        }

        foreach ($casts as $attribute => $cast) {
            if (! is_string($attribute) || ! is_string($cast)) {
                throw new RuntimeException(sprintf('Unsupported cast definition for [%s].', (string) $attribute));
            }
        }

        return $casts;
    }

    private function method(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() === $name) {
                return $method;
            }
        }

        return null;
    }
}
