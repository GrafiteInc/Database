<?php

namespace Grafite\Database;

use Illuminate\Support\ServiceProvider;
use Grafite\Database\Commands\TableEmpty;
use Grafite\Database\Commands\TableStart;
use Grafite\Database\Commands\DatabaseDrop;
use Grafite\Database\Commands\DatabaseSize;
use Grafite\Database\Commands\TableOptimize;
use Grafite\Database\Commands\DatabaseBackup;
use Grafite\Database\Commands\DatabaseCreate;
use Grafite\Database\Commands\DatabaseUpload;
use Grafite\Database\Commands\DatabaseRestore;
use Grafite\Database\Commands\DatabaseDownload;

class GrafiteDatabaseProvider extends ServiceProvider
{
    /**
     * Boot method.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/backup.php' => base_path('config/backup.php'),
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        /*
        |--------------------------------------------------------------------------
        | Register the Commands
        |--------------------------------------------------------------------------
        */
        $this->commands([
            DatabaseBackup::class,
            DatabaseRestore::class,
            DatabaseCreate::class,
            DatabaseDrop::class,
            DatabaseDownload::class,
            DatabaseUpload::class,
            DatabaseSize::class,
            TableStart::class,
            TableEmpty::class,
            TableOptimize::class,
        ]);
    }
}
