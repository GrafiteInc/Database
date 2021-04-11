<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseSize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:size {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get a database\'s size';

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

        $sql = 'SELECT
            table_schema as `database`,
            table_name AS `table`,
            round(((data_length + index_length) / 1024 / 1024), 2) `mb`
        FROM information_schema.TABLES
        ORDER BY (data_length + index_length) DESC;';

        $response = DB::select($sql);

        $data = collect($response)->filter(function ($item) use ($name) {
            return $item->database === $name;
        });

        $this->info(json_encode([
            'table_count' => $data->count(),
            'total_size' => round($data->pluck('mb')->sum(), 2),
        ]));
    }
}
