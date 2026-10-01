<?php

namespace SynergiTech\LaravelDocblocks\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class ModelFinder
{
    /**
     * @return iterable<string>
     */
    public function find(string $path): iterable
    {
        if (! is_dir($path)) {
            throw new RuntimeException("Models directory does not exist: {$path}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        $files = [];

        foreach ($iterator as $file) {
            if (
                $file instanceof SplFileInfo
                && $file->isFile()
                && $file->getExtension() === 'php'
            ) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        yield from $files;
    }
}
