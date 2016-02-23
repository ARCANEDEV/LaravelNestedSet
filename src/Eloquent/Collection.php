<?php namespace Arcanedev\LaravelNestedSet\Eloquent;

use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Arcanedev\LaravelNestedSet\Traits\NodeTrait;
use Illuminate\Database\Eloquent\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class     Collection
 *
 * @package  Arcanedev\Taxonomies\Utilities
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class Collection extends BaseCollection
{
    /**
     * Fill `parent` and `children` relationships for every node in the collection.
     *
     * This will overwrite any previously set relations.
     *
     * @return $this
     */
    public function linkNodes()
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $groupedNodes = $this->groupBy($this->first()->getParentIdName());

        /** @var NodeTrait|Model $node */
        foreach ($this->items as $node) {
            if ( ! $node->getParentId()) {
                $node->setRelation('parent', null);
            }

            $children = $groupedNodes->get($node->getKey(), [ ]);

            /** @var Model|NodeTrait $child */
            foreach ($children as $child) {
                $child->setRelation('parent', $node);
            }

            $node->setRelation('children', BaseCollection::make($children));
        }

        return $this;
    }

    /**
     * Build a tree from a list of nodes. Each item will have set children relation.
     * To successfully build tree "id", "_lft" and "parent_id" keys must present.
     * If `$root` is provided, the tree will contain only descendants of that node.
     *
     * @param  int|Model|null  $root
     *
     * @return self
     */
    public function toTree($root = null)
    {
        if ($this->isEmpty()) {
            return new static;
        }

        $this->linkNodes();

        $items = [ ];

        $root = $this->getRootNodeId($root);

        /** @var Model|NodeTrait $node */
        foreach ($this->items as $node) {
            if ($node->getParentId() == $root) {
                $items[] = $node;
            }
        }

        return new static($items);
    }

    /**
     * Get root node id.
     *
     * @param  mixed  $root
     *
     * @return int
     */
    protected function getRootNodeId($root = null)
    {
        if (NestedSet::isNode($root)) {
            return $root->getKey();
        }

        if ($root !== null) {
            return $root;
        }

        // If root node is not specified we take parent id of node with
        // least lft value as root node id.
        $leastValue = null;

        /** @var Model|NodeTrait $node */
        foreach ($this->items as $node) {
            if ($leastValue === null || $node->getLft() < $leastValue) {
                $leastValue = $node->getLft();
                $root = $node->getParentId();
            }
        }

        return $root;
    }
}
