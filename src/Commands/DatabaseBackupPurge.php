<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DatabaseBackupPurge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup-purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans out the database backup directory.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $backupStoragePath = config('backup.path', base_path('database/backups'));

        if (File::isDirectory($backupStoragePath)) {
            File::deleteDirectory($backupStoragePath);
        }

        $this->info('Backup directory now purged.');
    }
}
