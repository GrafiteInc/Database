<?php

use Grafite\Database\Traits\HasTree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TreeQueryTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'tree');
        $app['config']->set('database.connections.tree', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function setUp(): void
    {
        parent::setUp();

        Schema::create('tree_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
        });

        Schema::create('tree_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folder_id')->nullable();
            $table->string('name');
        });

        // 1 Root
        // ├── 2 Electronics
        // │   ├── 3 Phones
        // │   │   └── 5 Android
        // │   └── 4 Laptops
        // └── 6 Books
        // 7 Other (separate root)
        DB::table('tree_categories')->insert([
            ['id' => 1, 'parent_id' => null, 'name' => 'Root', 'active' => true],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Electronics', 'active' => true],
            ['id' => 3, 'parent_id' => 2, 'name' => 'Phones', 'active' => true],
            ['id' => 4, 'parent_id' => 2, 'name' => 'Laptops', 'active' => false],
            ['id' => 5, 'parent_id' => 3, 'name' => 'Android', 'active' => true],
            ['id' => 6, 'parent_id' => 1, 'name' => 'Books', 'active' => true],
            ['id' => 7, 'parent_id' => null, 'name' => 'Other', 'active' => true],
        ]);

        DB::table('tree_folders')->insert([
            ['id' => 1, 'folder_id' => null, 'name' => 'Home'],
            ['id' => 2, 'folder_id' => 1, 'name' => 'Docs'],
            ['id' => 3, 'folder_id' => 2, 'name' => 'Taxes'],
        ]);
    }

    protected function ids(Builder $query): array
    {
        return $query->pluck('id')->sort()->values()->all();
    }

    public function testMacrosAreRegistered()
    {
        foreach (['whereDescendantOf', 'whereDescendantOrSelfOf', 'whereAncestorOf', 'whereAncestorOrSelfOf'] as $macro) {
            $this->assertTrue(Builder::hasGlobalMacro($macro), $macro);
        }
    }

    public function testDescendants()
    {
        $this->assertEquals([2, 3, 4, 5, 6], $this->ids(TreeCategory::whereDescendantOf(1)));
        $this->assertEquals([3, 4, 5], $this->ids(TreeCategory::whereDescendantOf(2)));
        $this->assertEquals([], $this->ids(TreeCategory::whereDescendantOf(5)));
    }

    public function testDescendantsWithDepthLimit()
    {
        $this->assertEquals([2, 6], $this->ids(TreeCategory::whereDescendantOf(1, depth: 1)));
        $this->assertEquals([2, 3, 4, 6], $this->ids(TreeCategory::whereDescendantOf(1, depth: 2)));
    }

    public function testDescendantsOrSelf()
    {
        $this->assertEquals([2, 3, 4, 5], $this->ids(TreeCategory::whereDescendantOrSelfOf(2)));
    }

    public function testAncestors()
    {
        $this->assertEquals([1, 2, 3], $this->ids(TreeCategory::whereAncestorOf(5)));
        $this->assertEquals([1, 2, 3, 5], $this->ids(TreeCategory::whereAncestorOrSelfOf(5)));
        $this->assertEquals([], $this->ids(TreeCategory::whereAncestorOf(1)));
    }

    public function testOrderedAncestorsAreRootFirst()
    {
        $results = TreeCategory::whereAncestorOf(5, ordered: true)->get();

        $this->assertEquals(['Root', 'Electronics', 'Phones'], $results->pluck('name')->all());
        $this->assertEquals([3, 2, 1], $results->pluck('tree_depth')->map(fn ($d) => (int) $d)->all());
    }

    public function testOrderedDescendantsAreNearestFirst()
    {
        $depths = TreeCategory::whereDescendantOrSelfOf(1, ordered: true)
            ->get()
            ->pluck('tree_depth')
            ->map(fn ($d) => (int) $d)
            ->all();

        $this->assertEquals([0, 1, 1, 2, 2, 3], $depths);
    }

    public function testOrderedResultsAreDedupedAcrossSeeds()
    {
        // Phones (3) is a descendant of both 1 and 2: one row, at its smallest depth.
        $results = TreeCategory::whereDescendantOf([1, 2], ordered: true)->get();

        $this->assertCount(5, $results);
        $this->assertEquals(1, (int) $results->firstWhere('id', 3)->tree_depth);
    }

    public function testAcceptsModelsCollectionsAndMultipleSeeds()
    {
        $phones = TreeCategory::find(3);

        $this->assertEquals([5], $this->ids(TreeCategory::whereDescendantOf($phones)));
        $this->assertEquals([5], $this->ids(TreeCategory::whereDescendantOf([3, 6])));
        $this->assertEquals([5], $this->ids(TreeCategory::whereDescendantOf(TreeCategory::whereIn('id', [3, 6])->get())));
    }

    public function testEmptyInputReturnsNothing()
    {
        $this->assertEquals([], $this->ids(TreeCategory::whereDescendantOf([])));
        $this->assertEquals([], $this->ids(TreeCategory::whereAncestorOf([null])));
    }

    public function testComposesWithOtherClauses()
    {
        $this->assertEquals(4, TreeCategory::where('active', true)->whereDescendantOf(1)->count());
        $this->assertEquals(2, TreeCategory::whereDescendantOf(1, ordered: true)->where('active', true)->where('tree_categories.id', '>', 3)->count());
        $this->assertTrue(TreeCategory::whereDescendantOf(2)->where('name', 'Android')->exists());
    }

    public function testCustomParentColumn()
    {
        $this->assertEquals([2, 3], $this->ids(TreeFolder::whereDescendantOf(1, 'folder_id')));
        $this->assertEquals([1, 2], $this->ids(TreeFolder::whereAncestorOf(3, parentColumn: 'folder_id')));
    }

    public function testCyclicDataTerminates()
    {
        DB::table('tree_categories')->where('id', 1)->update(['parent_id' => 2]);

        $this->assertEquals([1, 2, 3, 4, 5, 6], $this->ids(TreeCategory::whereDescendantOf(1)));
        $this->assertEquals([1, 2], $this->ids(TreeCategory::whereAncestorOf(2)));
    }

    public function testTraitRelationsAndHelpers()
    {
        $phones = TreeCategory::find(3);

        $this->assertEquals('Electronics', $phones->parent->name);
        $this->assertEquals(['Android'], $phones->children->pluck('name')->all());
        $this->assertEquals([1, 7], TreeCategory::roots()->pluck('id')->sort()->values()->all());
        $this->assertFalse($phones->isRoot());
        $this->assertTrue(TreeCategory::find(1)->isRoot());
    }

    public function testTraitTreeMethods()
    {
        $electronics = TreeCategory::find(2);
        $android = TreeCategory::find(5);

        $this->assertEquals([3, 4, 5], $this->ids($electronics->descendants()));
        $this->assertEquals([3, 4], $this->ids($electronics->descendants(depth: 1)));
        $this->assertEquals([2, 3, 4, 5], $this->ids($electronics->descendantsAndSelf()));
        $this->assertEquals(['Root', 'Electronics', 'Phones'], $android->ancestors(ordered: true)->pluck('name')->all());
        $this->assertEquals(['Root', 'Electronics', 'Phones', 'Android'], $android->ancestorsAndSelf(ordered: true)->pluck('name')->all());
    }

    public function testTraitRespectsCustomParentColumn()
    {
        $taxes = TreeFolder::find(3);

        $this->assertEquals('folder_id', $taxes->getParentColumn());
        $this->assertEquals(['Home', 'Docs'], $taxes->ancestors(ordered: true)->pluck('name')->all());
        $this->assertEquals('Docs', $taxes->parent->name);
    }
}

class TreeCategory extends Model
{
    use HasTree;

    protected $table = 'tree_categories';
    protected $guarded = [];
    public $timestamps = false;
}

class TreeFolder extends Model
{
    use HasTree;

    protected $table = 'tree_folders';
    protected $guarded = [];
    public $timestamps = false;
    protected $parentColumn = 'folder_id';
}
