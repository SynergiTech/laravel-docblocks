<?php

namespace SynergiTech\LaravelDocblocks\Commands;

use Illuminate\Console\Command;

final class GenerateCommand extends Command
{
    protected $signature = 'laravel-docblocks:generate
                            {--dry-run : Show which models would be updated without modifying files}';

    protected $description = 'Generate PHPDoc for Eloquent model relationships and casts';

    public function handle(): int
    {
        $arguments = $this->option('dry-run') ? ['--dry-run' => true] : [];

        foreach ([
            'laravel-docblocks:model-relationships',
            'laravel-docblocks:model-casts',
        ] as $command) {
            if ($this->call($command, $arguments) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
