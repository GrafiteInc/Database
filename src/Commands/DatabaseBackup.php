<?php

namespace Grafite\Database\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\DbDumper\Databases\MySql;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--tables=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates dump file for the database.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Starting database backup');

        $backupStoragePath = config('backup.path', base_path('database/backups'));
        $backupName = config('backup.filename', 'db-backup');

        if (!File::isDirectory($backupStoragePath)) {
            File::makeDirectory($backupStoragePath);
        }

        $backupPath = "{$backupStoragePath}/{$backupName}.sql";
        $tables = $this->option('tables');

        if (! is_null($tables)) {
            $tables = explode(',', $tables);
        }

        if (file_exists($backupPath)) {
            $this->info('Copying old backup');
            $date = Carbon::now()->format('d-M-Y');
            $archivedBackup = "{$backupStoragePath}/{$backupName}-{$date}.sql";

            $contents = File::get($backupPath);
            File::put($archivedBackup, $contents);

            $this->info('Old backup copied');
        }

        $backup = MySql::create()
            ->setDbName(config('database.connections.mysql.database'))
            ->setUserName(config('database.connections.mysql.username'))
            ->setPassword(config('database.connections.mysql.password'));

        if (! is_null($tables)) {
            $backup->includeTables($tables);
            $backupName = $backupName . '-' . implode('-', $tables);
        }

        $backup->dumpToFile("{$backupStoragePath}/{$backupName}.sql");

        $this->info('Completed');
    }
}
