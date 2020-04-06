<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DatabaseDropTest extends TestCase
{
    public function testDropDatabase()
    {
        DB::shouldReceive('unprepared')
            ->once()
            ->with('DROP DATABASE `tester`');

        $this->artisan('db:drop tester')
            ->expectsConfirmation('Are you sure you want to drop database: tester?', 'yes')
            ->assertExitCode(0);
    }
}
