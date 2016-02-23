<?php namespace Arcanedev\LaravelNestedSet\Tests;

use Arcanedev\LaravelNestedSet\Tests\Models\MenuItem;
use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class     ScopedNodeTest
 *
 * @package  Arcanedev\Taxonomies\Tests
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class ScopedNodeTest extends TestCase
{
    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    public function setUp()
    {
        parent::setUp();

        $this->createMenusTable();
        $this->seedMenusTable();

        MenuItem::resetActionsPerformed();

        date_default_timezone_set('America/Denver');
    }

    public function tearDown()
    {
        $this->table('menu_items')->truncate();

        parent::tearDown();
    }

    /* ------------------------------------------------------------------------------------------------
     |  Test Functions
     | ------------------------------------------------------------------------------------------------
     */
    /** @test */
    public function it_can_assert_is_not_broken()
    {
        $this->assertTreeNotBroken(1);
        $this->assertTreeNotBroken(2);
    }

    /** @test */
    public function it_can_assure_moving_nodes_not_affecting_other_menus()
    {
        $node = MenuItem::where('menu_id', '=', 1)->first();

        $node->down();

        $node = MenuItem::where('menu_id', '=', 2)->first();

        $this->assertEquals(1, $node->getLft());
    }

    /** @test */
    public function it_can_be_scoped()
    {
        $node = MenuItem::scoped([ 'menu_id' => 2 ])->first();

        $this->assertEquals(3, $node->getKey());
    }

    /** @test */
    public function it_can_get_siblings()
    {
        $node     = MenuItem::find(1);
        $siblings = $node->getSiblings();

        $this->assertCount(1, $siblings);
        $this->assertEquals(2, $siblings->first()->getKey());

        $siblings = $node->getNextSiblings();

        $this->assertEquals(2, $siblings->first()->getKey());

        $siblings = MenuItem::find(2)->getPrevSiblings();

        $this->assertEquals(1, $siblings->first()->getKey());
    }

    /** @test */
    public function it_can_get_descendants()
    {
        $descendants = MenuItem::find(2)->getDescendants();

        $this->assertCount(1, $descendants);
        $this->assertEquals(5, $descendants->first()->getKey());
    }

    /** @test */
    public function it_can_get_ancestors()
    {
        $ancestors = MenuItem::find(5)->getAncestors();

        $this->assertCount(1, $ancestors);
        $this->assertEquals(2, $ancestors->first()->getKey());
    }

    /** @test */
    public function it_can_get_depth()
    {
        $node = MenuItem::scoped(['menu_id' => 1])
            ->withDepth()
            ->where('id', '=', 5)
            ->first();

        $this->assertEquals(1, $node->depth);

        $node   = MenuItem::find(2);
        $result = $node->children()->withDepth()->get();

        $this->assertEquals(1, $result->first()->depth);
    }

    /** @test */
    public function it_can_save_node_as_root()
    {
        $node = MenuItem::find(5);

        $node->saveAsRoot();

        $this->assertEquals(5, $node->getLft());

        $this->assertOtherScopeNotAffected();
    }

    /** @test */
    public function it_can_insert()
    {
        MenuItem::create([
            'menu_id'   => 1,
            'parent_id' => 5,
        ]);

        $this->assertOtherScopeNotAffected();
    }

    /*
     * @test
     *
     * @expectedException
     */
    public function it_must_fails_insertion_to_parent_from_other_scope()
    {
        MenuItem::create([
            'menu_id'   => 2,
            'parent_id' => 5,
        ]);
    }

    /** @test */
    public function it_can_delete()
    {
        MenuItem::find(2)->delete();

        $node = MenuItem::find(1);

        $this->assertEquals(2, $node->getRgt());

        $this->assertOtherScopeNotAffected();
    }

    /** @test */
    public function it_can_move_down()
    {
        $node = MenuItem::find(1);
        $this->assertTrue($node->down());

        $this->assertOtherScopeNotAffected();
    }

    /* ------------------------------------------------------------------------------------------------
     |  Assertion Functions
     | ------------------------------------------------------------------------------------------------
     */
    public function assertTreeNotBroken($menuId)
    {
        $this->assertFalse(MenuItem::scoped(['menu_id' => $menuId])->isBroken());
    }

    protected function assertOtherScopeNotAffected()
    {
        $this->assertEquals(1, MenuItem::find(3)->getLft());
    }

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    private function createMenusTable()
    {
        $schema = $this->db()->getSchemaBuilder();

        $schema->dropIfExists('menu_items');

        $schema->create('menu_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id');
            NestedSet::columns($table);
            $table->string('title')->nullable();
        });
    }

    private function seedMenusTable()
    {
        $data = include __DIR__ . '/fixtures/data/menu_items.php';

        $this->table('menu_items')->insert($data);
    }
}
