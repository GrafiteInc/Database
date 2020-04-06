<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseRestore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore';

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
        $this->info('Starting Database restore');

        $dbDump = base_path('database/db-snapshot-latest.sql');
        $dbDumpContents = file_get_contents($dbDump);
        $fileSize = filesize($dbDump) + 2000;

        DB::unprepared($dbDumpContents.' --max_allowed_packet='.$fileSize);

        $this->info('Completed');
    }
}
