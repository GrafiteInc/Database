<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseDrop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:drop {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop a database';

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
        $name = $this->argument('name');
        $sql = "DROP DATABASE `{$name}`";
        $response = 'Database: '.$name.' drop has been cancelled';

        if ($this->confirm('Are you sure you want to drop database: '.$name.'?')) {
            DB::unprepared($sql);
            $response = 'Database: '.$name.' has been dropped';
        }

        $this->info($response);
    }
}
