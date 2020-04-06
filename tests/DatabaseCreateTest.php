<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DatabaseCreateTest extends TestCase
{
    public function testCreateDatabase()
    {
        DB::shouldReceive('unprepared')
            ->once()
            ->with('CREATE DATABASE `tester` DEFAULT CHARACTER SET = `utf8mb4`');

        Artisan::call('db:create tester');
    }
}
