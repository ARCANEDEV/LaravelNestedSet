<?php namespace Arcanedev\LaravelNestedSet\Contracts;

/**
 * Interface  Nodeable
 *
 * @package   Arcanedev\LaravelNestedSet\Contracts
 * @author    ARCANEDEV <arcanedev.maroc@gmail.com>
 *
 * @property  int                                             $id
 * @property  int                                             $_lft
 * @property  int                                             $_rgt
 * @property  int                                             $parent_id
 * @property  \Arcanedev\LaravelNestedSet\Contracts\Nodeable  $parent
 * @property  \Illuminate\Database\Eloquent\Collection        $children
 */
interface Nodeable extends Eloquent
{
    /* ------------------------------------------------------------------------------------------------
     |  Getters & Setters
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get the lft key name.
     *
     * @return string
     */
    public function getLftName();

    /**
     * Get the rgt key name.
     *
     * @return string
     */
    public function getRgtName();

    /**
     * Get the parent id key name.
     *
     * @return string
     */
    public function getParentIdName();

    /**
     * Get the value of the model's lft key.
     *
     * @return int
     */
    public function getLft();

    /**
     * Set the value of the model's lft key.
     *
     * @param  int  $value
     *
     * @return self
     */
    public function setLft($value);

    /**
     * Get the value of the model's rgt key.
     *
     * @return int
     */
    public function getRgt();

    /**
     * Set the value of the model's rgt key.
     *
     * @param  int  $value
     *
     * @return self
     */
    public function setRgt($value);

    /**
     * Get the value of the model's parent id key.
     *
     * @return int
     */
    public function getParentId();

    /**
     * Set the value of the model's parent id key.
     *
     * @param  int  $value
     *
     * @return self
     */
    public function setParentId($value);

    /**
     * Set the value of model's parent id key.
     *
     * Behind the scenes node is appended to found parent node.
     *
     * @param  int  $value
     *
     * @throws \Exception If parent node doesn't exists
     */
    public function setParentIdAttribute($value);

    /**
     * Get the boundaries.
     *
     * @return array
     */
    public function getBounds();

    /**
     * Returns node that is next to current node without constraining to siblings.
     * This can be either a next sibling or a next sibling of the parent node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getNextNode(array $columns = ['*']);

    /**
     * Returns node that is before current node without constraining to siblings.
     * This can be either a prev sibling or parent node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getPrevNode(array $columns = ['*']);

    /**
     * Get the ancestors nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function getAncestors(array $columns = ['*']);

    /**
     * Get the descendants nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function getDescendants(array $columns = ['*']);

    /**
     * Get the siblings nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function getSiblings(array $columns = ['*']);

    /**
     * Get the next siblings nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function getNextSiblings(array $columns = ['*']);

    /**
     * Get the previous siblings nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function getPrevSiblings(array $columns = ['*']);

    /**
     * Get the next sibling node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getNextSibling(array $columns = ['*']);

    /**
     * Get the previous sibling node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getPrevSibling(array $columns = ['*']);

    /**
     * Get node height (rgt - lft + 1).
     *
     * @return int
     */
    public function getNodeHeight();

    /**
     * Get number of descendant nodes.
     *
     * @return int
     */
    public function getDescendantCount();

    /**
     * Set raw node.
     *
     * @param  int  $lft
     * @param  int  $rgt
     * @param  int  $parentId
     *
     * @return self
     */
    public function rawNode($lft, $rgt, $parentId);

    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Refresh node's crucial attributes.
     */
    public function refreshNode();

    /**
     * Get query for siblings after the node.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function nextSiblings();

    /**
     * Get query for siblings before the node.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function prevSiblings();

    /**
     * Get query for nodes after current node.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function nextNodes();

    /**
     * Get query for nodes before current node in reversed order.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function prevNodes();

    /**
     * Get query for ancestors to the node not including the node itself.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function ancestors();

    /**
     * Make this node a root node.
     *
     * @return self
     */
    public function makeRoot();

    /**
     * Save node as root.
     *
     * @return bool
     */
    public function saveAsRoot();

    /**
     * Append and save a node.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function appendNode(Nodeable $node);

    /**
     * Prepend and save a node.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function prependNode(Nodeable $node);

    /**
     * Append a node to the new parent.
     *
     * @param  self  $parent
     *
     * @return self
     */
    public function appendToNode(Nodeable $parent);

    /**
     * Prepend a node to the new parent.
     *
     * @param  self  $parent
     *
     * @return self
     */
    public function prependToNode(Nodeable $parent);

    /**
     * Append or prepend a node to parent.
     *
     * @param  self  $parent
     * @param  bool  $prepend
     *
     * @return self
     */
    public function appendOrPrependTo(Nodeable $parent, $prepend = false);

    /**
     * Insert self after a node.
     *
     * @param  self  $node
     *
     * @return self
     */
    public function afterNode(Nodeable $node);

    /**
     * Insert self before node.
     *
     * @param  self  $node
     *
     * @return self
     */
    public function beforeNode(Nodeable $node);

    /**
     * Set before or after a node.
     *
     * @param  self  $node
     * @param  bool  $after
     *
     * @return self
     */
    public function beforeOrAfterNode(Nodeable $node, $after = false);

    /**
     * Insert after a node and save.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function insertAfterNode(Nodeable $node);

    /**
     * Insert before a node and save.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function insertBeforeNode(Nodeable $node);

    /**
     * Move node up given amount of positions.
     *
     * @param  int  $amount
     *
     * @return bool
     */
    public function up($amount = 1);

    /**
     * Move node down given amount of positions.
     *
     * @param  int  $amount
     *
     * @return bool
     */
    public function down($amount = 1);

    /* ------------------------------------------------------------------------------------------------
     |  Check Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get whether node is root.
     *
     * @return bool
     */
    public function isRoot();

    /**
     * Get whether a node is a descendant of other node.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function isDescendantOf(Nodeable $node);

    /**
     * Get whether the node is immediate children of other node.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function isChildOf(Nodeable $node);

    /**
     * Get whether the node is a sibling of another node.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function isSiblingOf(Nodeable $node);

    /**
     * Get whether the node is an ancestor of other node, including immediate parent.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function isAncestorOf(Nodeable $node);

    /**
     * Get whether the node has moved since last save.
     *
     * @return bool
     */
    public function hasMoved();
}
