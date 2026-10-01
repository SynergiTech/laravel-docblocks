<?php

namespace SynergiTech\LaravelDocblocks\Support;

use PhpParser\Comment\Doc;

final class Docblock
{
    /**
     * @param array<string, string> $casts
     */
    public static function casts(?Doc $existingDocblock, array $casts): string
    {
        $returnBlock = self::castsReturnBlock($casts);

        if ($existingDocblock === null) {
            return implode("\n", [
                '/**',
                " * {$returnBlock}",
                ' */',
            ]);
        }

        $docblock = $existingDocblock->getText();
        $pattern = '/@return\s+array\{.*?^\s*\*\s*\}/ms';

        if (preg_match($pattern, $docblock)) {
            $updatedDocblock = preg_replace(
                $pattern,
                $returnBlock,
                $docblock,
                1,
            ) ?? $docblock;

            return preg_replace(
                '/^(\s*)\/\*\*\s+(@return\b.*)$/m',
                '$1/**' . "\n" . '$1 * $2',
                $updatedDocblock,
                1,
            ) ?? $updatedDocblock;
        }

        return preg_replace(
            '/\s*\*\/$/',
            "\n *\n * {$returnBlock}\n */",
            $docblock,
            1,
        ) ?? $docblock;
    }

    public static function returnType(?Doc $existingDocblock, string $returnType): string
    {
        if ($existingDocblock === null) {
            return implode("\n", [
                '/**',
                " * @return {$returnType}",
                ' */',
            ]);
        }

        $docblock = $existingDocblock->getText();

        if (! str_contains($docblock, '@return')) {
            return preg_replace(
                '/\s*\*\/$/',
                "\n *\n * @return {$returnType}\n */",
                $docblock,
                1,
            ) ?? $docblock;
        }

        $lineEnding = str_contains($docblock, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\r\n|\r|\n/', $docblock);

        if ($lines === false) {
            return $docblock;
        }

        $returnFound = false;
        $updatedLines = [];

        foreach ($lines as $line) {
            if (! str_contains($line, '@return')) {
                $updatedLines[] = $line;

                continue;
            }

            if ($returnFound && preg_match('/^\s*(?:\/\*\*|\*)?\s*@return\b/', $line)) {
                if (str_contains($line, '*/')) {
                    $updatedLines[] = ' */';
                }

                continue;
            }

            $returnFound = true;
            $updatedLine = preg_replace(
                '/@return\s+.*?(?=\s*\*\/|$)/',
                '@return ' . $returnType,
                $line,
                1,
            );

            if ($updatedLine === null) {
                $updatedLines[] = $line;

                continue;
            }

            if (preg_match('/^(\s*)\/\*\*\s*@return\b(.*)$/', $updatedLine, $matches)) {
                $updatedLines[] = $matches[1] . '/**';
                $updatedLines[] = $matches[1] . ' * @return ' . trim($matches[2], ' / *');

                if (str_contains($matches[2], '*/')) {
                    $updatedLines[] = $matches[1] . ' */';
                }

                continue;
            }

            $updatedLines[] = $updatedLine;
        }

        return implode($lineEnding, $updatedLines);
    }

    /**
     * @param array<string, string> $casts
     */
    private static function castsReturnBlock(array $casts): string
    {
        $lines = ['@return array{'];

        foreach ($casts as $attribute => $cast) {
            $lines[] = sprintf(
                " *     %s: '%s',",
                $attribute,
                str_replace('\\', '\\\\', $cast),
            );
        }

        if ($casts !== []) {
            $last = array_key_last($lines);
            $lines[$last] = rtrim($lines[$last], ',');
        }

        $lines[] = ' * }';

        return implode("\n", $lines);
    }
}
