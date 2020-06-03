<?php

namespace Grafite\Database;

use Illuminate\Support\ServiceProvider;
use Grafite\Database\Commands\DatabaseDrop;
use Grafite\Database\Commands\DatabaseBackup;
use Grafite\Database\Commands\DatabaseCreate;
use Grafite\Database\Commands\DatabaseRestore;

class GrafiteDatabaseProvider extends ServiceProvider
{
    /**
     * Boot method.
     */
    public function boot()
    {
        // do nothing
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
        ]);
    }
}
