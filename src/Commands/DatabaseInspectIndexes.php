<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseInspectIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:inspect-indexes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect indexes of the database.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $sql = <<<'SQL'
            SELECT
                t.table_schema,
                t.table_name,
                s.index_name,
                s.count_star AS index_usage_count
            FROM performance_schema.table_io_waits_summary_by_index_usage s
            JOIN information_schema.tables t
                ON s.object_schema = t.table_schema
                AND s.object_name = t.table_name
            WHERE s.index_name IS NOT NULL
            AND t.table_schema NOT IN ('mysql', 'performance_schema', 'information_schema')
            ORDER BY s.count_star ASC;
        SQL;

        $result = DB::select($sql);

        $this->table(
            $result[0] ? array_keys((array) $result[0]) : [],
            array_map(function ($item) {
                return (array) $item;
            }, $result)
        );
    }
}
