<?php namespace Arcanedev\LaravelNestedSet\Eloquent;

use Arcanedev\LaravelNestedSet\NodeTrait;
use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Class     Collection
 *
 * @package  Arcanedev\Taxonomies\Utilities
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class Collection extends EloquentCollection
{
    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Fill `parent` and `children` relationships for every node in the collection.
     *
     * This will overwrite any previously set relations.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function linkNodes()
    {
        if ($this->isEmpty()) return $this;

        $groupedNodes = $this->groupBy($this->first()->getParentIdName());

        /** @var  NodeTrait|\Illuminate\Database\Eloquent\Model  $node */
        foreach ($this->items as $node) {
            if ( ! $node->getParentId()) {
                $node->setRelation('parent', null);
            }

            $children = $groupedNodes->get($node->getKey(), []);

            /** @var  NodeTrait|\Illuminate\Database\Eloquent\Model  $child */
            foreach ($children as $child) {
                $child->setRelation('parent', $node);
            }

            $node->setRelation('children', EloquentCollection::make($children));
        }

        return $this;
    }

    /**
     * Build a tree from a list of nodes. Each item will have set children relation.
     * To successfully build tree "id", "_lft" and "parent_id" keys must present.
     * If `$root` is provided, the tree will contain only descendants of that node.
     *
     * @param  mixed  $root
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function toTree($root = false)
    {
        if ($this->isEmpty()) return new static;

        $this->linkNodes();

        $items = [];
        $root  = $this->getRootNodeId($root);

        /** @var  NodeTrait|\Illuminate\Database\Eloquent\Model  $node */
        foreach ($this->items as $node) {
            if ($node->getParentId() == $root) {
                $items[] = $node;
            }
        }

        return new static($items);
    }

    /**
     * Build a list of nodes that retain the order that they were pulled from the database.
     *
     * @param  bool  $root
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function toFlatTree($root = false)
    {
        $result = new static;

        if ($this->isEmpty()) return $result;

        return $result->flattenTree(
            $this->groupBy($this->first()->getParentIdName()),
            $this->getRootNodeId($root)
        );
    }

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get root node id.
     *
     * @param  mixed  $root
     *
     * @return int
     */
    protected function getRootNodeId($root = false)
    {
        if (NestedSet::isNode($root)) {
            return $root->getKey();
        }

        if ($root !== false) return $root;

        // If root node is not specified we take parent id of node with
        // least lft value as root node id.
        $leastValue = null;

        /** @var  NodeTrait|\Illuminate\Database\Eloquent\Model  $node */
        foreach ($this->items as $node) {
            if ($leastValue === null || $node->getLft() < $leastValue) {
                $leastValue = $node->getLft();
                $root       = $node->getParentId();
            }
        }

        return $root;
    }

    /**
     * Flatten a tree into a non recursive array.
     *
     * @param  \Arcanedev\LaravelNestedSet\Eloquent\Collection  $groupedNodes
     * @param  mixed                                            $parentId
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    protected function flattenTree(self $groupedNodes, $parentId)
    {
        foreach ($groupedNodes->get($parentId, []) as $node) {
            $this->push($node);
            $this->flattenTree($groupedNodes, $node->getKey());
        }

        return $this;
    }
}
