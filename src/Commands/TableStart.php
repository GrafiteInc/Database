<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TableStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:table-start {table} {--number=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the increment of a table.';

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
        $number = $this->option('number');

        if (is_null($number)) {
            $number = mt_rand(999, 99999);
        }

        $sql = "ALTER TABLE {$table} AUTO_INCREMENT = {$number};";

        DB::unprepared($sql);

        $this->info("Set increment of: {$table} to {$number}.");
    }
}
