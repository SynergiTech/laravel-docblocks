# Laravel Docblocks

Automatically generate PHPDoc types for various elements within Laravel project.

## Installation

```sh
composer require --dev synergitech/laravel-docblocks
```

Laravel discovers the package automatically.

## Commands

```sh
php artisan laravel-docblocks:generate # runs all generators at once

# Run generators manually:
php artisan laravel-docblocks:model-relationships
php artisan laravel-docblocks:model-casts
```

Pass `--dry-run` to any command to preview changes without writing files.
