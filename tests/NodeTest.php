<?php namespace Arcanedev\LaravelNestedSet\Tests;

use Arcanedev\LaravelNestedSet\Contracts\Nodeable;
use Arcanedev\LaravelNestedSet\Tests\Models\Category;
use Arcanedev\LaravelNestedSet\Eloquent\Collection;
use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class     NodeTest
 *
 * @package  Arcanedev\Taxonomies\Tests
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class NodeTest extends TestCase
{
    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    public function setUp()
    {
        parent::setUp();

        $this->createCategoriesTable();
        $this->seedCategoriesTable();

        Category::resetActionsPerformed();

        date_default_timezone_set('America/Denver');
    }

    public function tearDown()
    {
        $this->table('categories')->truncate();
    }

    /* ------------------------------------------------------------------------------------------------
     |  Test Functions
     | ------------------------------------------------------------------------------------------------
     */
    /** @test */
    public function assert_tree_is_not_broken()
    {
        $this->assertTreeNotBroken();
        $this->assertFalse(Category::isBroken());
    }

    /** @test */
    public function it_can_gets_node_data()
    {
        $expected = [
            '_lft' => 3,
            '_rgt' => 4,
        ];

        $this->assertEquals($expected, Category::getNodeData(3));
    }

    /** @test */
    public function it_can_gets_plain_node_data()
    {
        $expected = [3, 4];

        $this->assertEquals($expected, Category::getPlainNodeData(3));
    }

    /** @test */
    public function it_can_receives_valid_values_when_appended_to()
    {
        $node     = new Category(['name' => 'test']);
        $root     = Category::root();
        $expected = [
            $root->_rgt,
            $root->_rgt + 1,
            $root->id,
        ];

        $root->appendNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertEquals($expected, $this->nodeValues($node));
        $this->assertTreeNotBroken();
        $this->assertFalse($node->isDirty());
        $this->assertTrue($node->isDescendantOf($root));
    }

    /** @test */
    public function it_can_receives_valid_values_when_prepended_to()
    {
        $root     = Category::root();
        $node     = new Category(['name' => 'test']);
        $expected = [
            $root->_lft + 1,
            $root->_lft + 2,
            $root->id,
        ];

        $root->prependNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertEquals($expected, $this->nodeValues($node));
        $this->assertTreeNotBroken();
        $this->assertTrue($node->isDescendantOf($root));
        $this->assertTrue($root->isAncestorOf($node));
        $this->assertTrue($node->isChildOf($root));
    }

    /** @test */
    public function it_can_receives_valid_values_when_inserted_after()
    {
        $target   = $this->findCategory('apple');
        $node     = new Category([ 'name' => 'test' ]);
        $expected = [
            $target->_rgt + 1,
            $target->_rgt + 2,
            $target->parent->id,
        ];

        $node->afterNode($target)->save();

        $this->assertTrue($node->hasMoved());
        $this->assertEquals($expected, $this->nodeValues($node));
        $this->assertTreeNotBroken();
        $this->assertFalse($node->isDirty());
        $this->assertTrue($node->isSiblingOf($target));
    }

    /** @test */
    public function it_can_receives_valid_values_when_inserted_before()
    {
        $target   = $this->findCategory('apple');
        $node     = new Category(['name' => 'test']);
        $expected = [
            $target->_lft,
            $target->_lft + 1,
            $target->parent->id,
        ];

        $node->beforeNode($target)->save();

        $this->assertTrue($node->hasMoved());
        $this->assertEquals($expected, $this->nodeValues($node));
        $this->assertTreeNotBroken();
    }

    /** @test */
    public function it_can_moves_down_a_category()
    {
        $node   = $this->findCategory('apple');
        $target = $this->findCategory('mobile');

        $target->appendNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertNodeReceivesValidValues($node);
        $this->assertTreeNotBroken();
    }

    /** @test */
    public function it_can_moves_up()
    {
        $node   = $this->findCategory('samsung');
        $target = $this->findCategory('notebooks');

        $target->appendNode($node);

        $this->assertTrue($node->hasMoved());
        $this->assertTreeNotBroken();
        $this->assertNodeReceivesValidValues($node);
    }

    /**
     * @test
     *
     * @expectedException         \LogicException
     * @expectedExceptionMessage  Node must not be a descendant.
     */
    public function it_must_fails_to_insert_into_child()
    {
        $node   = $this->findCategory('notebooks');
        $target = $node->children()->first();

        $node->afterNode($target)->save();
    }

    /**
     * @test
     *
     * @expectedException         \LogicException
     * @expectedExceptionMessage  Node must not be a descendant.
     */
    public function it_must_fails_to_append_into_itself()
    {
        $node = $this->findCategory('notebooks');

        $node->appendToNode($node)->save();
    }

    /**
     * @test
     *
     * @expectedException         \LogicException
     * @expectedExceptionMessage  Node must not be a descendant.
     */
    public function it_must_fails_to_prepend_into_itself()
    {
        $node = $this->findCategory('notebooks');

        $node->prependToNode($node)->save();
    }

    /** @test */
    public function it_can_works_without_root()
    {
        $result = Category::withoutRoot()->pluck('name');

        $this->assertNotEquals('store', $result);
    }

    /** @test */
    public function it_can_get_ancestors_without_node_itself()
    {
        $category  = $this->findCategory('apple');
        $ancestors = $category->ancestors()->lists('name');

        $this->assertCount(2, $ancestors);
        $this->assertEquals(['store', 'notebooks'], $ancestors->toArray());
    }

    /** @test */
    public function it_can_get_ancestors_without_node_itself_by_static_method()
    {
        /** @var  \Illuminate\Support\Collection  $ancestors */
        $ancestors = Category::ancestorsOf(3)->lists('name');

        $this->assertCount(2, $ancestors);
        $this->assertEquals(['store', 'notebooks'], $ancestors->toArray());
    }

    /** @test */
    public function it_can_gets_ancestors_direct()
    {
        /** @var  \Illuminate\Support\Collection  $ancestors */
        $ancestors = Category::find(8)->getAncestors()->lists('id');

        $this->assertCount(3, $ancestors);
        $this->assertEquals([1, 5, 7], $ancestors->toArray());
    }

    /** @test */
    public function it_can_get_descendants()
    {
        $category = $this->findCategory('mobile');
        $expected = ['nokia', 'samsung', 'galaxy', 'sony', 'lenovo'];

        $this->assertCount(
            $category->getDescendantCount(),
            $category->descendants()->lists('name')
        );
        $this->assertEquals(
            $expected,
            $category->descendants()->lists('name')->toArray()
        );

        $this->assertCount(
            $category->getDescendantCount(),
            $category->getDescendants()->lists('name')
        );
        $this->assertEquals(
            $expected,
            $category->getDescendants()->lists('name')->toArray()
        );
    }

    /** @test */
    public function it_can_works_with_depth()
    {
        /** @var \Illuminate\Support\Collection $categories */
        $categories = Category::withDepth()->limit(4)->lists('depth');
        $expected   = [0, 1, 2, 2];

        $this->assertEquals($expected, $categories->toArray());
    }

    /** @test */
    public function it_can_works_with_depth_by_a_custom_key()
    {
        $category = Category::whereIsRoot()
            ->withDepth('level')
            ->first();

        $this->assertNotNull($category['level']);
    }

    /** @test */
    public function it_can_works_with_depth_by_a_default_key()
    {
        /** @var  Category  $category */
        $category = Category::withDepth()->first();

        $this->assertNotNull($category->name);
    }

    /** @test */
    public function it_can_appends_node_with_parent_id_attribute_accessor()
    {
        $node = new Category([
            'name'      => 'lg',
            'parent_id' => 5
        ]);
        $node->save();

        $this->assertEquals(5, $node->parent_id);
        $this->assertEquals(5, $node->getParentId());

        $node->parent_id = null;
        $node->save();

        $this->assertEquals(null, $node->parent_id);
        $this->assertTrue($node->isRoot());
    }

    /**
     * @test
     *
     * @expectedException \Exception
     */
    public function it_must_fails_to_save_node_until_not_inserted()
    {
        (new Category)->save();
    }

    /** @test */
    public function it_can_delete_node_with_descendants()
    {
        $node = $this->findCategory('mobile');
        $node->forceDelete();

        $this->assertTreeNotBroken();
        $this->assertEquals(0, Category::whereIn('id', [5, 6, 7, 8, 9])->count());
        $this->assertEquals(8, Category::root()->getRgt());
    }

    /** @test */
    public function it_can_handle_soft_delete()
    {
        $root    = Category::root();
        $samsung = $this->findCategory('samsung');
        $samsung->delete();

        $this->assertTreeNotBroken();
        $this->assertNull($this->findCategory('galaxy'));

        sleep(1);

        $node = $this->findCategory('mobile');
        $node->delete();

        $this->assertEquals(0, Category::whereIn('id', [5, 6, 7, 8, 9])->count());

        $originalRgt = $root->getRgt();
        $root->refreshNode();

        $this->assertEquals($originalRgt, $root->getRgt());

        $node = $this->findCategory('mobile', true);
        $node->restore();

        $this->assertNull($this->findCategory('samsung'));
    }

    /** @test */
    public function it_must_delete_soft_deleted_nodes_when_parent_is_deleted()
    {
        $this->findCategory('samsung')->delete();
        $this->findCategory('mobile')->forceDelete();

        $this->assertTreeNotBroken();

        $this->assertNull($this->findCategory('samsung', true));
        $this->assertNull($this->findCategory('sony'));
    }

    /**
     * @test
     *
     * @expectedException         \LogicException
     * @expectedExceptionMessage  Node must exists.
     */
    public function it_must_fails_to_save_node_until_parent_is_saved()
    {
        $node   = new Category(['title' => 'Node']);
        $parent = new Category(['title' => 'Parent']);

        $node->appendToNode($parent)->save();
    }

    /** @test */
    public function it_can_get_Siblings()
    {
        $node = $this->findCategory('samsung');

        $this->assertEquals([6, 9, 10], $node->siblings()->lists('id')->toArray());
        $this->assertEquals([6, 9, 10], $node->getSiblings()->lists('id')->toArray());

        $this->assertEquals([9, 10],    $node->nextSiblings()->lists('id')->toArray());
        $this->assertEquals([9, 10],    $node->getNextSiblings()->lists('id')->toArray());

        $this->assertEquals([6],        $node->prevSiblings()->lists('id')->toArray());
        $this->assertEquals([6],        $node->getPrevSiblings()->lists('id')->toArray());

        $this->assertEquals(9, $node->getNextSibling()->id);
        $this->assertEquals(6, $node->getPrevSibling()->id);
    }

    /** @test */
    public function it_can_make_a_reversed_fetches()
    {
        $node     = $this->findCategory('sony');
        $siblings = $node->prevSiblings()->reversed()->value('id');

        $this->assertEquals(7, $siblings);
    }

    /** @test */
    public function it_convert_to_tree_with_the_default_order()
    {
        $tree = Category::whereBetween('_lft', [8, 17])
            ->defaultOrder()
            ->get()
            ->toTree();

        $this->assertCount(1, $tree);

        $root = $tree->first();

        $this->assertEquals('mobile', $root->name);
        $this->assertCount(4,         $root->children);
    }

    /** @test */
    public function it_can_convert_to_tree_with_a_custom_order()
    {
        $tree = Category::whereBetween('_lft', [8, 17])
            ->orderBy('title')
            ->get()
            ->toTree();

        $this->assertCount(1, $tree);

        $root = $tree->first();

        $this->assertEquals('mobile', $root->name);
        $this->assertCount(4,         $root->children);
        $this->assertEquals($root,    $root->children->first()->parent);
    }

    /** @test */
    public function it_can_convert_to_tree_with_a_specified_root()
    {
        $node  = $this->findCategory('mobile');
        $nodes = Category::whereBetween('_lft', [8, 17])->get();

        $this->assertCount(4, Collection::make($nodes)->toTree(5));
        $this->assertCount(4, Collection::make($nodes)->toTree($node));
    }

    /** @test */
    public function it_can_convert_to_tree_with_default_order_and_multiple_root_nodes()
    {
        $tree = Category::withoutRoot()->get()->toTree();

        $this->assertCount(2, $tree);
    }

    /** @test */
    public function it_can_convert_to_tree_with_the_provided_root_item_id()
    {
        $tree = Category::whereBetween('_lft', [8, 17])->get()->toTree(5);

        $this->assertCount(4, $tree);

        $root = $tree[1];
        $this->assertEquals('samsung', $root->name);
        $this->assertCount(1, $root->children);
    }

    /** @test */
    public function it_can_convert_to_flat_tree()
    {
        $node = $this->findCategory('mobile');
        $tree = $node->descendants()->orderBy('name')->get()->toFlatTree();

        $this->assertCount(5, $tree);
        $this->assertEquals('samsung', $tree[2]->name);
        $this->assertEquals('galaxy',  $tree[3]->name);
    }

    /** @test */
    public function it_can_retrieves_next_node()
    {
        /** @var Category $next */
        $next = $this->findCategory('apple')
            ->nextNodes()
            ->first();

        $this->assertEquals('lenovo', $next->name);
    }

    /** @test */
    public function it_can_retrieves_previous_node()
    {
        $node = $this->findCategory('apple');
        $prev = $node->getPrevNode();

        $this->assertEquals('notebooks', $prev->name);
    }

    /** @test */
    public function it_can_works_with_multiple_appendage()
    {
        $parent = $this->findCategory('mobile');
        $child  = new Category(['name' => 'test']);

        $parent->appendNode($child);
        $child->appendNode(new Category(['name' => 'sub']));
        $parent->appendNode(new Category(['name' => 'test2']));

        $this->assertTreeNotBroken();
    }

    /** @test */
    public function it_can_save_a_new_node_as_root_as_default()
    {
        $node = new Category(['name' => 'test']);
        $node->save();

        $this->assertEquals(23, $node->_lft);
        $this->assertTreeNotBroken();
        $this->assertTrue($node->isRoot());
    }

    /** @test */
    public function it_can_save_an_existent_node_as_root()
    {
        $node = $this->findCategory('apple');
        $node->saveAsRoot();

        $this->assertTreeNotBroken();
        $this->assertTrue($node->isRoot());
    }

    /** @test */
    public function it_can_moves_down_a_node_several_positions()
    {
        $node = $this->findCategory('nokia');

        $this->assertTrue($node->down(2));
        $this->assertEquals(15, $node->_lft);
    }

    /** @test */
    public function it_can_moves_up_a_node_several_positions()
    {
        $node = $this->findCategory('sony');

        $this->assertTrue($node->up(2));
        $this->assertEquals(9, $node->_lft);
    }

    /** @test */
    public function it_counts_tree_errors()
    {
        $expected = [
            'oddness'        => 0,
            'duplicates'     => 0,
            'wrong_parent'   => 0,
            'missing_parent' => 0,
        ];

        $this->assertEquals($expected, Category::countErrors());

        Category::where('id', 5)->update(['_lft' => 14]);
        Category::where('id', 8)->update(['parent_id' => 2]);
        Category::where('id', 11)->update(['_lft' => 20]);
        Category::where('id', 4)->update(['parent_id' => 24]);

        $errors = Category::countErrors();

        $this->assertEquals(1, $errors['oddness']);
        $this->assertEquals(2, $errors['duplicates']);
        $this->assertEquals(1, $errors['missing_parent']);
    }

    /** @test */
    public function it_can_creates_node()
    {
        $node = Category::create(['name' => 'test']);

        $this->assertEquals(23, $node->getLft());
    }

    /** @test */
    public function it_can_creates_via_relationship()
    {
        $node  = $this->findCategory('apple');
        $node->children()->create(['name' => 'test']);

        $this->assertTreeNotBroken();
    }

    /** @test */
    public function it_can_creates_tree()
    {
        $node = Category::create([
            'name'     => 'test',
            'children' => [
                ['name' => 'test2'],
                ['name' => 'test3'],
            ],
        ]);

        $this->assertTreeNotBroken();
        $this->assertTrue(isset($node->children));
        $this->assertCount(2, $node->children);
    }

    /** @test */
    public function it_must_get_empty_descendants_on_non_existing_node()
    {
        $node = new Category;

        $this->assertTrue($node->getDescendants()->isEmpty());
    }

    /**
     * @test
     *
     * @expectedException \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function it_must_fails_where_descendants_of_is_not_found()
    {
        Category::whereDescendantOf(124)->get();
    }

    /** @test */
    public function it_can_get_ancestors_by_a_node()
    {
        $category  = $this->findCategory('apple');
        $ancestors = Category::whereAncestorOf($category)->lists('id');

        $this->assertEquals([1, 2], $ancestors->toArray());
    }

    /** @test */
    public function it_can_get_descendants_by_a_node()
    {
        $category   = $this->findCategory('notebooks');
        $categories = Category::whereDescendantOf($category)->lists('id');

        $this->assertEquals([3, 4], $categories->toArray());
    }

    /** @test */
    public function it_can_delete_multiple_nodes_without_breaking_the_tree()
    {
        $category = $this->findCategory('mobile');

        foreach ($category->children()->take(2)->get() as $child) {
            /** @var Category $child */
            $child->forceDelete();
        }

        $this->assertTreeNotBroken();
    }

    /** @test */
    public function it_can_fix_tree()
    {
        $updates = [
            5  => ['_lft' => 14],
            8  => ['parent_id' => 2],
            11 => ['_lft' => 20],
            2  => ['parent_id' => 24],
        ];

        foreach ($updates as $id => $update) {
            Category::where('id', $id)->update($update);
        }

        $fixed = Category::fixTree();

        $this->assertTrue($fixed > 0);
        $this->assertTreeNotBroken();

        /** @var Category $category */
        $category = Category::find(8);

        $this->assertEquals(2, $category->getParentId());

        $category = Category::find(2);

        $this->assertNull($category->getParentId());
    }

    /** @test */
    public function it_can_check_the_parent_id_dirtiness()
    {
        $node            = $this->findCategory('apple');
        $node->parent_id = 5;

        $this->assertTrue($node->isDirty('parent_id'));
    }

    /** @test */
    public function it_can_check_dirty_movements()
    {
        $node      = $this->findCategory('apple');
        $otherNode = $this->findCategory('samsung');

        $this->assertFalse($node->isDirty());

        $node->afterNode($otherNode);

        $this->assertTrue($node->isDirty());

        $node      = $this->findCategory('apple');
        $otherNode = $this->findCategory('samsung');

        $this->assertFalse($node->isDirty());

        $node->appendToNode($otherNode);

        $this->assertTrue($node->isDirty());
    }

    /** @test */
    public function it_can_move_root_nodes()
    {
        $node = $this->findCategory('store');
        $node->down();

        $this->assertEquals(3, $node->getLft());
    }

    /** @test */
    public function it_has_descendants_relation()
    {
        $descendants = $this->findCategory('notebooks')->descendants;

        $this->assertEquals(2,       $descendants->count());
        $this->assertEquals('apple', $descendants->first()->name);
    }

    /** @test */
    public function it_can_eagerly_loaded_the_descendants_nodes()
    {
        /** @var Category $nodes */
        $nodes = Category::whereIn('id', [ 2, 5 ])->get();

        $nodes->load('descendants');

        $this->assertEquals(2, $nodes->count());
        $this->assertTrue($nodes->first()->relationLoaded('descendants'));
    }

    /** @test */
    public function it_can_query_descendants_Relation()
    {
        $nodes = Category::has('descendants')->whereIn('id', [ 2, 3 ])->get();

        $this->assertEquals(1, $nodes->count());
        $this->assertEquals(2, $nodes->first()->getKey());

        $nodes = Category::has('descendants', '>', 2)->get();

        $this->assertEquals(2, $nodes->count());
        $this->assertEquals(1, $nodes[0]->getKey());
        $this->assertEquals(5, $nodes[1]->getKey());
    }

    /** @test */
    public function it_can_query_parent_relation()
    {
        $nodes = Category::has('parent')->whereIn('id', [1, 2]);

        $this->assertEquals(1, $nodes->count());
        $this->assertEquals(2, $nodes->first()->getKey());
    }

    /** @test */
    public function it_can_rebuild_tree()
    {
        $fixed = Category::rebuildTree([
            [
                'id'       => 1,
                'children' => [
                    [
                        'id'       => 3,
                        'name'     => 'apple v2',
                        'children' => [
                            ['name' => 'new node'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($fixed > 0);
        $this->assertTreeNotBroken();

        /** @var Category $node */
        $node = Category::find(3);

        $this->assertEquals(1, $node->getParentId());
        $this->assertEquals('apple v2', $node->name);

        $node = $this->findCategory('new node');

        $this->assertNotNull($node);
        $this->assertEquals(3, $node->getParentId());
    }

    /** @test */
    public function it_can_rebuild_tree_with_deletion()
    {
        Category::rebuildTree([
            ['name' => 'all deleted']
        ], true);

        $nodes = Category::get();

        $this->assertCount(1, $nodes);
        $this->assertEquals('all deleted', $nodes->first()->name);
    }

    /**
     * @test
     *
     * @expectedException \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function it_must_fails_rebuild_tree_with_invalid_pk()
    {
        Category::rebuildTree([
            ['id' => 24]
        ]);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Assertion Functions
     | ------------------------------------------------------------------------------------------------
     */
    protected function assertTreeNotBroken($table = 'categories')
    {
        $checks   = [];

        // Check if lft and rgt values are ok
        $checks[] = "from $table where _lft >= _rgt or (_rgt - _lft) % 2 = 0";

        // Check if lft and rgt values are unique
        $checks[] = "from $table c1, $table c2 where c1.id <> c2.id and ".
            "(c1._lft=c2._lft or c1._rgt=c2._rgt or c1._lft=c2._rgt or c1._rgt=c2._lft)";

        // Check if parent_id is set correctly
        $checks[] = "from $table c, $table p, $table m where c.parent_id=p.id and m.id <> p.id and m.id <> c.id and ".
            "(c._lft not between p._lft and p._rgt or c._lft between m._lft and m._rgt and m._lft between p._lft and p._rgt)";

        foreach ($checks as $i => $check) {
            $checks[$i] = 'select 1 as error '.$check;
        }

        $sql    = 'select max(error) as errors from ('.implode(' union ', $checks).') _';

        $actual = $this->db()->selectOne($sql);

        $this->assertEquals(null, $actual->errors, "The tree structure of $table is broken!");
        $this->assertEquals(['errors' => null], (array) $actual, "The tree structure of $table is broken!");
    }

    /**
     * @param  \Arcanedev\LaravelNestedSet\Contracts\Nodeable  $node
     */
    protected function assertNodeReceivesValidValues(Nodeable $node)
    {
        $category = $this->findCategory($node->name);

        $this->assertEquals(
            [$category->getLft(), $category->getRgt()],
            [$node->getLft(),     $node->getRgt()],
            'Node is not synced with database after save.'
        );
    }

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Create the categories table.
     */
    private function createCategoriesTable()
    {
        $schema = $this->db()->getSchemaBuilder();

        $schema->dropIfExists('categories');

        $schema->create('categories', function (Blueprint $table) {
            $table->increments('id');
            NestedSet::columns($table);
            $table->string('name');
            $table->softDeletes();
        });
    }

    /**
     * Seed the categories table.
     */
    private function seedCategoriesTable()
    {
        $this->table('categories')->insert(
            include __DIR__ . '/fixtures/data/categories.php'
        );
    }

    /**
     * Find a category.
     *
     * @param  string  $name
     * @param  bool    $withTrashed
     *
     * @return \Arcanedev\LaravelNestedSet\Tests\Models\Category
     */
    private function findCategory($name, $withTrashed = false)
    {
        $category = new Category;
        $category = $withTrashed ? $category->withTrashed() : $category->newQuery();

        return $category->where('name', $name)->first();
    }

    /**
     * Get the node values.
     *
     * @param  \Arcanedev\LaravelNestedSet\Contracts\Nodeable  $node
     *
     * @return array
     */
    private function nodeValues($node)
    {
        return [
            $node->_lft,
            $node->_rgt,
            $node->parent_id
        ];
    }

    /**
     * Dump the tree (Only for tests).
     *
     * @param  null  $items
     */
    private function dumpTree($items = null)
    {
        if ($items === null) {
            $items = Category::withTrashed()->defaultOrder()->get();
        }

        /** @var  \Arcanedev\LaravelNestedSet\Tests\Models\Category  $item */
        foreach ($items as $item) {
            echo PHP_EOL . ($item->trashed() ? '-' : '+') . ' ' . $item->name . " " . $item->getKey() . ' ' . $item->getLft() . " " . $item->getRgt() . ' ' . $item->getParentId();
        }
    }
}
