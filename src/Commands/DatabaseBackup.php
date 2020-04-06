<?php

namespace Grafite\Database\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Spatie\DbDumper\Databases\MySql;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

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

        $backup = base_path('database/db-snapshot-latest.sql');

        if (file_exists($backup)) {
            $this->info('Copying old backup');
            $date = Carbon::now()->format('d-M-Y');
            $archivedBackup = base_path('database/db-snapshot-'.$date.'.sql');

            $contents = file_get_contents($backup);
            file_put_contents($archivedBackup, $contents);
            $this->info('Old backup copied');
        }

        MySql::create()
            ->setDbName(config('database.connections.mysql.database'))
            ->setUserName(config('database.connections.mysql.username'))
            ->setPassword(config('database.connections.mysql.password'))
            ->dumpToFile(base_path('database/db-snapshot-latest.sql'));

        $this->info('Completed');
    }
}
