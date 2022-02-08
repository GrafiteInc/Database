<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TableOptimize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:table-optimize {table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize a database table';

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

        $sql = "OPTIMIZE table `{$table}`";

        DB::unprepared($sql);

        $this->info("Table: $table has been optimized");
    }
}
