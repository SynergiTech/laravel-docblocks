<?php

namespace SynergiTech\LaravelDocblocks\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use SynergiTech\LaravelDocblocks\Support\Docblock;
use Throwable;

final class DocumentModelRelationshipsCommand extends AbstractDocumentCommand
{
    protected $signature = 'laravel-docblocks:model-relationships
                            {--dry-run : Show which models would be updated without modifying files}';

    protected $description = 'Add PHPStan generic PHPDoc return types to Eloquent relationship methods';

    /**
     * @param ReflectionClass<Model> $reflection
     */
    protected function document(ReflectionClass $reflection, Class_ $modelClass): bool
    {
        $methods = $this->methods($modelClass);

        if ($methods === []) {
            return false;
        }

        /** @var Model $model */
        $model = $reflection->newInstanceWithoutConstructor();
        $changed = false;

        foreach ($this->relationshipMethods($reflection) as $reflectionMethod) {
            $method = $methods[$reflectionMethod->getName()] ?? null;

            if ($method === null) {
                continue;
            }

            try {
                $relation = $reflectionMethod->invoke($model);
            } catch (Throwable $exception) {
                $this->warn(sprintf(
                    'Skipping %s::%s(): could not invoke relationship: %s',
                    $reflection->getName(),
                    $reflectionMethod->getName(),
                    $exception->getMessage(),
                ));

                continue;
            }

            if (! $relation instanceof Relation) {
                $this->warn(sprintf(
                    'Skipping %s::%s(): method did not return an Eloquent relation.',
                    $reflection->getName(),
                    $reflectionMethod->getName(),
                ));

                continue;
            }

            $returnType = $this->relationshipReturnType($relation);

            if ($returnType === null) {
                $this->warn(sprintf(
                    'Skipping %s::%s(): unsupported relationship [%s].',
                    $reflection->getName(),
                    $reflectionMethod->getName(),
                    $relation::class,
                ));

                continue;
            }

            $docblock = Docblock::returnType($method->getDocComment(), $returnType);

            if ($method->getDocComment()?->getText() === $docblock) {
                continue;
            }

            $method->setDocComment(new Doc($docblock));
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param ReflectionClass<Model> $reflection
     * @return array<int, ReflectionMethod>
     */
    private function relationshipMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
                continue;
            }

            $returnClass = $returnType->getName();

            if ($returnClass === Relation::class || is_subclass_of($returnClass, Relation::class)) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     */
    private function relationshipReturnType(Relation $relation): ?string
    {
        $related = $this->shortClassName($relation->getRelated()::class);

        return match (true) {
            $relation instanceof MorphTo => 'MorphTo<Model, $this>',
            $relation instanceof MorphOne => "MorphOne<{$related}, \$this>",
            $relation instanceof MorphMany => "MorphMany<{$related}, \$this>",
            $relation instanceof MorphToMany => "MorphToMany<{$related}, \$this>",
            $relation instanceof HasOneThrough => sprintf(
                'HasOneThrough<%s, %s, $this>',
                $related,
                $this->throughModel($relation),
            ),
            $relation instanceof HasManyThrough => sprintf(
                'HasManyThrough<%s, %s, $this>',
                $related,
                $this->throughModel($relation),
            ),
            $relation instanceof BelongsTo => "BelongsTo<{$related}, \$this>",
            $relation instanceof HasOne => "HasOne<{$related}, \$this>",
            $relation instanceof HasMany => "HasMany<{$related}, \$this>",
            $relation instanceof BelongsToMany => sprintf(
                'BelongsToMany<%s, $this%s>',
                $related,
                $relation->getPivotClass() === Pivot::class
                    ? ''
                    : ', ' . $this->shortClassName($relation->getPivotClass()),
            ),
            default => null,
        };
    }

    /**
     * @param HasOneThrough<Model, Model, Model>|HasManyThrough<Model, Model, Model> $relation
     */
    private function throughModel(HasOneThrough|HasManyThrough $relation): string
    {
        $reflection = new ReflectionClass($relation);

        while (! $reflection->hasProperty('throughParent')) {
            $reflection = $reflection->getParentClass();

            if ($reflection === false) {
                throw new RuntimeException('Could not determine through model for ' . $relation::class);
            }
        }

        $throughParent = $reflection->getProperty('throughParent')->getValue($relation);

        if (! $throughParent instanceof Model) {
            throw new RuntimeException('Invalid through model for ' . $relation::class);
        }

        return $this->shortClassName($throughParent::class);
    }

    /**
     * @return array<string, ClassMethod>
     */
    private function methods(Class_ $class): array
    {
        $methods = [];

        foreach ($class->getMethods() as $method) {
            $methods[$method->name->toString()] = $method;
        }

        return $methods;
    }

    private function shortClassName(string $class): string
    {
        if (! class_exists($class)) {
            throw new RuntimeException("Class does not exist: {$class}");
        }

        return (new ReflectionClass($class))->getShortName();
    }
}
