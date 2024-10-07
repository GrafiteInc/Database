<?php

namespace Grafite\Database\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TableEmpty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:table-empty {table} {--column=} {--days=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Empty a table.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $table = $this->argument('table');
        $column = $this->option('column') ?? 'failed_at';
        $days = $this->option('days');

        $sql = "truncate ${table}";

        if ($days) {
            $sql = "delete from ${table} where ${column} <= '" . Carbon::now()->subDays($days) . "'";
        }

        DB::unprepared($sql);

        $this->info("Emptied the table: {$table}.");
    }
}
