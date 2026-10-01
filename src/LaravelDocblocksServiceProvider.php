<?php

namespace SynergiTech\LaravelDocblocks;

use Illuminate\Support\ServiceProvider;
use SynergiTech\LaravelDocblocks\Commands\DocumentModelCastsCommand;
use SynergiTech\LaravelDocblocks\Commands\DocumentModelRelationshipsCommand;
use SynergiTech\LaravelDocblocks\Commands\GenerateCommand;

final class LaravelDocblocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DocumentModelCastsCommand::class,
                DocumentModelRelationshipsCommand::class,
                GenerateCommand::class,
            ]);
        }
    }
}
