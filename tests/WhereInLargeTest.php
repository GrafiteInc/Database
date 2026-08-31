<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WhereInLargeTest extends TestCase
{
    public function testMacroIsRegistered()
    {
        $this->assertTrue(Builder::hasGlobalMacro('whereInLarge'));
    }

    public function testSmallSetFallsBackToWhereIn()
    {
        $query = WhereInLargeModel::query()->whereInLarge('id', [1, 2, 3]);

        $sql = $query->toSql();

        // Under the threshold: a plain IN clause, no temp-table join.
        $this->assertStringContainsString('in (?, ?, ?)', $sql);
        $this->assertStringNotContainsString('grafite_temp_ids_', $sql);
        $this->assertEquals([1, 2, 3], $query->getBindings());
    }

    public function testSmallSetDedupesAndDropsNulls()
    {
        $query = WhereInLargeModel::query()->whereInLarge('id', [1, 1, null, 2]);

        $this->assertEquals([1, 2], array_values($query->getBindings()));
    }

    public function testThresholdIsConfigurable()
    {
        // Two ids with a threshold of 1 pushes us over the limit, so on a
        // non-MySQL driver we expect the temp-table statement to blow up
        // rather than silently produce a whereIn.
        $this->expectException(\Throwable::class);

        WhereInLargeModel::query()->whereInLarge('id', [1, 2], 1)->toSql();
    }
}

class WhereInLargeModel extends Model
{
    protected $table = 'documents';
    protected $guarded = [];
}
