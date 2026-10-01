<?php

namespace SynergiTech\LaravelDocblocks\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use SynergiTech\LaravelDocblocks\Support\ModelFinder;
use SynergiTech\LaravelDocblocks\Support\PhpFile;
use Throwable;

abstract class AbstractDocumentCommand extends Command
{
    public function handle(ModelFinder $modelFinder): int
    {
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($modelFinder->find(app_path('Models')) as $path) {
            try {
                $file = PhpFile::fromPath($path);
                $changedClasses = [];

                foreach ($file->classes() as $class => $modelClass) {
                    if (! class_exists($class)) {
                        $this->warn("Skipping {$class}: class could not be loaded.");
                        $skipped++;

                        continue;
                    }

                    /** @var ReflectionClass<Model> $reflection */
                    $reflection = new ReflectionClass($class);

                    if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                        $skipped++;

                        continue;
                    }

                    if ($this->document($reflection, $modelClass)) {
                        $changedClasses[] = $class;
                    } else {
                        $this->line("Unchanged: {$class}");
                        $skipped++;
                    }
                }

                if ($changedClasses === []) {
                    continue;
                }

                $newCode = $file->render();

                if ($newCode === $file->code()) {
                    foreach ($changedClasses as $class) {
                        $this->line("Unchanged: {$class}");
                        $skipped++;
                    }

                    continue;
                }

                foreach ($changedClasses as $class) {
                    $updated++;
                    $this->option('dry-run')
                        ? $this->warn("Would update: {$class}")
                        : $this->info("Updated: {$class}");
                }

                if (! $this->option('dry-run') && file_put_contents($file->path(), $newCode) === false) {
                    throw new \RuntimeException("Could not write file: {$file->path()}");
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$path}: {$exception->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("{$updated} updated, {$skipped} skipped, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param ReflectionClass<Model> $reflection
     */
    abstract protected function document(ReflectionClass $reflection, \PhpParser\Node\Stmt\Class_ $modelClass): bool;
}
