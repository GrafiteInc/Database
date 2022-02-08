<?php

use Illuminate\Support\Facades\DB;

class DatabaseTableOptimizeTest extends TestCase
{
    public function testOptimizeTable()
    {
        DB::shouldReceive('unprepared')
            ->once()
            ->with('OPTIMIZE table `tester`');

        $this->artisan('db:table-optimize tester')
            ->assertExitCode(0);
    }
}
