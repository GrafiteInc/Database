<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseRestore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore {--backup=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore a dump file for the database.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $backupPath = $this->option('backup');

        if (is_null($backupPath)) {
            $backupStoragePath = config('backup.path', base_path('database/backups'));
            $backupName = config('backup.filename', 'db-backup');

            $backupPath = "{$backupStoragePath}/{$backupName}.sql";
        }

        $this->info('Starting Database restore');

        $dbDumpContents = File::get($backupPath);
        $fileSize = File::size($backupPath) + 2000;

        DB::unprepared($dbDumpContents.' --max_allowed_packet='.$fileSize);

        $this->info('Completed');
    }
}
