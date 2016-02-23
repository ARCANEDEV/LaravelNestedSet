<?php namespace Arcanedev\LaravelNestedSet\Traits;

use Arcanedev\LaravelNestedSet\Eloquent\DescendantsRelation;
use Arcanedev\LaravelNestedSet\Eloquent\Collection;
use Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder;
use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

/**
 * Class     NodeTrait
 *
 * @package  Arcanedev\Taxonomies\Traits
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 *
 * @property  array  $attributes
 * @property  array  $original
 * @property  bool   $exists
 * @property  bool   $forceDeleting
 *
 * @method  static  bool   isBroken()
 * @method  static  array  getNodeData($id, $required = false)
 * @method  static  array  getPlainNodeData($id, $required = false)
 *
 * @method  \Illuminate\Database\Eloquent\Relations\BelongsTo  belongsTo(string $related, string $foreignKey = null, string $otherKey = null, string $relation = null)
 * @method  \Illuminate\Database\Eloquent\Relations\HasMany    hasMany(string $related, string $foreignKey = null, string $localKey = null)
 */
trait NodeTrait
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Pending operation.
     *
     * @var array
     */
    protected $pending;

    /**
     * Whether the node has moved since last save.
     *
     * @var bool
     */
    protected $moved = false;

    /**
     * @var \Carbon\Carbon
     */
    public static $deletedAt;

    /**
     * Keep track of the number of performed operations.
     *
     * @var int
     */
    public static $actionsPerformed = 0;

    /* ------------------------------------------------------------------------------------------------
     |  Boot Function
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Sign on model events.
     */
    public static function bootNodeTrait()
    {
        static::saving(function (self $model) {
            $model->getConnection()->beginTransaction();

            return $model->callPendingAction();
        });

        static::saved(function (self $model) {
            $model->getConnection()->commit();
        });

        static::deleting(function (self $model) {
            $model->getConnection()->beginTransaction();

            // We will need fresh data to delete node safely
            $model->refreshNode();
        });

        static::deleted(function (self $model) {
            $model->deleteDescendants();

            $model->getConnection()->commit();
        });

        if (static::usesSoftDelete()) {
            static::restoring(function (self $model) {
                $model->getConnection()->beginTransaction();

                static::$deletedAt = $model->{$model->getDeletedAtColumn()};
            });

            static::restored(function (self $model) {
                $model->restoreDescendants(static::$deletedAt);

                $model->getConnection()->commit();
            });
        }
    }

    /* ------------------------------------------------------------------------------------------------
     |  Getters & Setters
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get the lft key name.
     *
     * @return string
     */
    public function getLftName()
    {
        return NestedSet::LFT;
    }

    /**
     * Get the rgt key name.
     *
     * @return string
     */
    public function getRgtName()
    {
        return NestedSet::RGT;
    }

    /**
     * Get the parent id key name.
     *
     * @return string
     */
    public function getParentIdName()
    {
        return NestedSet::PARENT_ID;
    }

    /**
     * Get the value of the model's lft key.
     *
     * @return int
     */
    public function getLft()
    {
        return $this->getAttributeValue($this->getLftName());
    }

    /**
     * Set the value of the model's lft key.
     *
     * @param  int  $value
     *
     * @return self
     */
    public function setLft($value)
    {
        $this->attributes[$this->getLftName()] = $value;

        return $this;
    }

    /**
     * Get the value of the model's rgt key.
     *
     * @return int
     */
    public function getRgt()
    {
        return $this->getAttributeValue($this->getRgtName());
    }

    /**
     * Set the value of the model's rgt key.
     *
     * @param  int  $value
     *
     * @return self
     */
    public function setRgt($value)
    {
        $this->attributes[$this->getRgtName()] = $value;

        return $this;
    }

    /**
     * Get the value of the model's parent id key.
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->getAttributeValue($this->getParentIdName());
    }

    /**
     * Set the value of the model's parent id key.
     *
     * @param  int  $value
     *
     * @return self
     */
    public function setParentId($value)
    {
        $this->attributes[$this->getParentIdName()] = $value;

        return $this;
    }

    /**
     * Apply parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $value
     *
     * @return self
     */
    protected function setParent($value)
    {
        $this->setParentId($value ? $value->getKey() : null)
            ->setRelation('parent', $value);

        return $this;
    }

    /**
     * Get the boundaries.
     *
     * @return array
     */
    public function getBounds()
    {
        return [$this->getLft(), $this->getRgt()];
    }

    /**
     * Set the lft and rgt boundaries to null.
     *
     * @return self
     */
    protected function dirtyBounds()
    {
        return $this->setLft(null)->setRgt(null);
    }

    /**
     * Returns node that is next to current node without constraining to siblings.
     * This can be either a next sibling or a next sibling of the parent node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getNextNode(array $columns = ['*'])
    {
        return $this->nextNodes()->first($columns);
    }

    /**
     * Returns node that is before current node without constraining to siblings.
     * This can be either a prev sibling or parent node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getPrevNode(array $columns = ['*'])
    {
        return $this->prevNodes()->first($columns);
    }

    /**
     * Get the ancestors nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function getAncestors(array $columns = ['*'])
    {
        return $this->newScopedQuery()
            ->defaultOrder()
            ->ancestorsOf($this->getKey(), $columns);
    }

    /**
     * Get the descendants nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection|self[]
     */
    public function getDescendants(array $columns = ['*'])
    {
        return $this->descendants()->get($columns);
    }

    /**
     * Get the siblings nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection|self[]
     */
    public function getSiblings(array $columns = ['*'])
    {
        return $this->siblings()->defaultOrder()->get($columns);
    }

    /**
     * Get the next siblings nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection|self[]
     */
    public function getNextSiblings(array $columns = ['*'])
    {
        return $this->nextSiblings()->get($columns);
    }

    /**
     * Get the previous siblings nodes.
     *
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection|self[]
     */
    public function getPrevSiblings(array $columns = ['*'])
    {
        return $this->prevSiblings()->get($columns);
    }

    /**
     * Get the next sibling node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getNextSibling(array $columns = ['*'])
    {
        return $this->nextSiblings()->first($columns);
    }

    /**
     * Get the previous sibling node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function getPrevSibling(array $columns = ['*'])
    {
        return $this->prevSiblings()->reversed()->first($columns);
    }

    /**
     * Get node height (rgt - lft + 1).
     *
     * @return int
     */
    public function getNodeHeight()
    {
        return $this->exists
            ? $this->getRgt() - $this->getLft() + 1
            : 2;
    }

    /**
     * Get number of descendant nodes.
     *
     * @return int
     */
    public function getDescendantCount()
    {
        return (int) ceil($this->getNodeHeight() / 2) - 1;
    }

    /**
     * Get an attribute array of all arrayable relations.
     *
     * @return array
     */
    protected function getArrayableRelations()
    {
        $result = parent::getArrayableRelations();

        unset($result['parent']);

        return $result;
    }

    /**
     * Set an action.
     *
     * @param  string  $action
     *
     * @return $this
     */
    protected function setAction($action)
    {
        $this->pending = func_get_args();

        return $this;
    }

    /**
     * Call pending action.
     *
     * @return null|false
     */
    protected function callPendingAction()
    {
        $this->moved = false;

        if ( ! $this->pending && ! $this->exists) {
            $this->makeRoot();
        }

        if ( ! $this->pending) return;

        $method        = 'action'.ucfirst(array_shift($this->pending));
        $parameters    = $this->pending;

        $this->pending = null;
        $this->moved   = call_user_func_array([ $this, $method ], $parameters);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Relationships
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Relation to the parent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(get_class($this), $this->getParentIdName())
            ->setModel($this);
    }

    /**
     * Relation to children.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(get_class($this), $this->getParentIdName())
            ->setModel($this);
    }

    /**
     * Get query for descendants of the node.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\DescendantsRelation
     */
    public function descendants()
    {
        return new DescendantsRelation($this->newScopedQuery(), $this);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Make a root node.
     */
    protected function actionRoot()
    {
        // Simplest case that do not affect other nodes.
        if ( ! $this->exists) {
            $cut = $this->getLowerBound() + 1;

            $this->setLft($cut);
            $this->setRgt($cut + 1);

            return true;
        }

        if ($this->isRoot()) {
            return false;
        }

        // Reset parent object
        $this->setParent(null);

        return $this->insertAt($this->getLowerBound() + 1);
    }

    /**
     * Get the lower bound.
     *
     * @return int
     */
    protected function getLowerBound()
    {
        return (int) $this->newNestedSetQuery()->max($this->getRgtName());
    }

    /**
     * Append or prepend a node to the parent.
     *
     * @param  self  $parent
     * @param  bool  $prepend
     *
     * @return bool
     */
    protected function actionAppendOrPrepend(self $parent, $prepend = false)
    {
        $parent->refreshNode();

        $cut = $prepend ? $parent->getLft() + 1 : $parent->getRgt();

        if ( ! $this->insertAt($cut)) {
            return false;
        }

        $parent->refreshNode();

        return true;
    }

    /**
     * Insert node before or after another node.
     *
     * @param  self  $node
     * @param  bool  $after
     *
     * @return bool
     */
    protected function actionBeforeOrAfter(self $node, $after = false)
    {
        $node->refreshNode();

        return $this->insertAt($after ? $node->getRgt() + 1 : $node->getLft());
    }

    /**
     * Refresh node's crucial attributes.
     */
    public function refreshNode()
    {
        if ( ! $this->exists || static::$actionsPerformed === 0) {
            return;
        }

        $attributes = $this->newNestedSetQuery()->getNodeData($this->getKey());

        $this->attributes = array_merge($this->attributes, $attributes);
        $this->original   = array_merge($this->original,   $attributes);
    }

    /**
     * Get query for siblings of the node.
     *
     * @param  mixed  $dir
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function siblings($dir = null)
    {
        switch ($dir) {
            case NestedSet::AFTER:
                $query = $this->nextNodes();
                break;

            case NestedSet::BEFORE:
                $query = $this->prevNodes();
                break;

            default:
                $query = $this->newScopedQuery()
                    ->defaultOrder()
                    ->where($this->getKeyName(), '<>', $this->getKey());
                break;
        }

        $query->where($this->getParentIdName(), '=', $this->getParentId());

        return $query;
    }

    /**
     * Get query for siblings after the node.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function nextSiblings()
    {
        return $this->siblings(NestedSet::AFTER);
    }

    /**
     * Get query for siblings before the node.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function prevSiblings()
    {
        return $this->siblings(NestedSet::BEFORE);
    }

    /**
     * Get query for nodes after current node.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function nextNodes()
    {
        return $this->newScopedQuery()
                    ->whereIsAfter($this->getKey())
                    ->defaultOrder();
    }

    /**
     * Get query for nodes before current node in reversed order.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function prevNodes()
    {
        return $this->newScopedQuery()
                    ->whereIsBefore($this->getKey())
                    ->reversed();
    }

    /**
     * Get query for ancestors to the node not including the node itself.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function ancestors()
    {
        return $this->newScopedQuery()
                    ->whereAncestorOf($this->getKey())
                    ->defaultOrder();
    }

    /**
     * Make this node a root node.
     *
     * @return self
     */
    public function makeRoot()
    {
        return $this->setAction('root');
    }

    /**
     * Save node as root.
     *
     * @return bool
     */
    public function saveAsRoot()
    {
        if ($this->exists && $this->isRoot()) {
            return true;
        }

        return $this->makeRoot()->save();
    }

    /**
     * Append and save a node.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function appendNode(self $node)
    {
        return $node->appendToNode($this)->save();
    }

    /**
     * Prepend and save a node.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function prependNode(self $node)
    {
        return $node->prependToNode($this)->save();
    }

    /**
     * Append a node to the new parent.
     *
     * @param  self  $parent
     *
     * @return self
     */
    public function appendToNode(self $parent)
    {
        return $this->appendOrPrependTo($parent);
    }

    /**
     * Prepend a node to the new parent.
     *
     * @param  self  $parent
     *
     * @return self
     */
    public function prependToNode(self $parent)
    {
        return $this->appendOrPrependTo($parent, true);
    }

    /**
     * @param  self  $parent
     * @param  bool  $prepend
     *
     * @return self
     */
    public function appendOrPrependTo(self $parent, $prepend = false)
    {
        $this->assertNodeExists($parent)
             ->assertNotDescendant($parent);

        $this->setParent($parent)->dirtyBounds();

        return $this->setAction('appendOrPrepend', $parent, $prepend);
    }

    /**
     * Insert self after a node.
     *
     * @param  self  $node
     *
     * @return self
     */
    public function afterNode(self $node)
    {
        return $this->beforeOrAfterNode($node, true);
    }

    /**
     * Insert self before node.
     *
     * @param  self  $node
     *
     * @return self
     */
    public function beforeNode(self $node)
    {
        return $this->beforeOrAfterNode($node);
    }

    /**
     * @param  self  $node
     * @param  bool  $after
     *
     * @return self
     */
    public function beforeOrAfterNode(self $node, $after = false)
    {
        $this->assertNodeExists($node)->assertNotDescendant($node);

        if ( ! $this->isSiblingOf($node)) {
            $this->setParent($node->getRelationValue('parent'));
        }

        $this->dirtyBounds();

        return $this->setAction('beforeOrAfter', $node, $after);
    }

    /**
     * Insert self after a node and save.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function insertAfterNode(self $node)
    {
        return $this->afterNode($node)->save();
    }

    /**
     * Insert self before a node and save.
     *
     * @param  self  $node
     *
     * @return bool
     */
    public function insertBeforeNode(self $node)
    {
        if ( ! $this->beforeNode($node)->save()) {
            return false;
        }

        // We'll update the target node since it will be moved
        $node->refreshNode();

        return true;
    }

    /**
     * Move node up given amount of positions.
     *
     * @param  int  $amount
     *
     * @return bool
     */
    public function up($amount = 1)
    {
        if ($sibling = $this->prevSiblings()->skip($amount - 1)->first()) {
            return $this->insertBeforeNode($sibling);
        }

        return false;
    }

    /**
     * Move node down given amount of positions.
     *
     * @param  int  $amount
     *
     * @return bool
     */
    public function down($amount = 1)
    {
        if ($sibling = $this->nextSiblings()->skip($amount - 1)->first()) {
            return $this->insertAfterNode($sibling);
        }

        return false;
    }

    /**
     * Insert node at specific position.
     *
     * @param  int  $position
     *
     * @return bool
     */
    protected function insertAt($position)
    {
        ++static::$actionsPerformed;

        $result = $this->exists
            ? $this->moveNode($position)
            : $this->insertNode($position);

        return $result;
    }

    /**
     * Move a node to the new position.
     *
     * @param  int  $position
     *
     * @return int
     */
    protected function moveNode($position)
    {
        $updated = $this->newNestedSetQuery()
                        ->moveNode($this->getKey(), $position) > 0;

        if ($updated) {
            $this->refreshNode();
        }

        return $updated;
    }

    /**
     * Insert new node at specified position.
     *
     * @param  int  $position
     *
     * @return bool
     */
    protected function insertNode($position)
    {
        $this->newNestedSetQuery()->makeGap($position, 2);

        $height = $this->getNodeHeight();

        $this->setLft($position);
        $this->setRgt($position + $height - 1);

        return true;
    }

    /**
     * Update the tree when the node is removed physically.
     */
    protected function deleteDescendants()
    {
        $lft = $this->getLft();
        $rgt = $this->getRgt();

        $method = $this->usesSoftDelete() && $this->forceDeleting
            ? 'forceDelete'
            : 'delete';

        $this->newQuery()
             ->applyNestedSetScope()
             ->whereNodeBetween([ $lft + 1, $rgt ])
             ->{$method}();

        if ($this->hardDeleting()) {
            $height = $rgt - $lft + 1;

            $this->newNestedSetQuery()->makeGap($rgt + 1, -$height);

            // In case if user wants to re-create the node
            $this->makeRoot();

            static::$actionsPerformed++;
        }
    }

    /**
     * Restore the descendants.
     *
     * @param $deletedAt
     */
    protected function restoreDescendants($deletedAt)
    {
        $this->newQuery()
             ->applyNestedSetScope()
             ->whereNodeBetween([ $this->getLft() + 1, $this->getRgt() ])
             ->where($this->getDeletedAtColumn(), '>=', $deletedAt)
             ->applyScopes()
             ->restore();
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new QueryBuilder($query);
    }

    /**
     * Get a new base query that includes deleted nodes.
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    public function newNestedSetQuery($table = null)
    {
        $builder = $this->usesSoftDelete()
            ? $this->withTrashed()
            : $this->newQuery();

        return $this->applyNestedSetScope($builder, $table);
    }

    /**
     * @param  string|null  $table
     *
     * @return mixed
     */
    public function newScopedQuery($table = null)
    {
        return $this->applyNestedSetScope($this->newQuery(), $table);
    }

    /**
     * @param  mixed   $query
     * @param  string  $table
     *
     * @return mixed
     */
    public function applyNestedSetScope($query, $table = null)
    {
        if ( ! $scoped = $this->getScopeAttributes()) {
            return $query;
        }

        if ( ! $table) {
            $table = $this->getTable();
        }

        foreach ($scoped as $attribute) {
            $query->where("$table.$attribute", '=', $this->getAttributeValue($attribute));
        }

        return $query;
    }

    /**
     * @return array
     */
    protected function getScopeAttributes()
    {
        return null;
    }

    /**
     * @param  array  $attributes
     *
     * @return self
     */
    public static function scoped(array $attributes)
    {
        $instance = new static;

        $instance->setRawAttributes($attributes);

        return $instance->newScopedQuery();
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Save a new model and return the instance.
     *
     * Use `children` key on `$attributes` to create child nodes.
     *
     * @param  array  $attributes
     * @param  self   $parent
     *
     * @return static
     */
    public static function create(array $attributes = [], self $parent = null)
    {
        $children = array_pull($attributes, 'children');
        $instance = new static($attributes);

        if ($parent) {
            $instance->appendToNode($parent);
        }

        $instance->save();

        // Now create children
        $relation = new EloquentCollection;

        foreach ((array) $children as $child) {
            $relation->add($child = static::create($child, $instance));

            $child->setRelation('parent', $instance);
        }

        return $instance->setRelation('children', $relation);
    }

    /**
     * Set the value of model's parent id key.
     *
     * Behind the scenes node is appended to found parent node.
     *
     * @param  int  $value
     *
     * @throws Exception If parent node doesn't exists
     */
    public function setParentIdAttribute($value)
    {
        if ($this->getParentId() == $value) return;

        if ($value) {
            $this->appendToNode($this->newScopedQuery()->findOrFail($value));
        } else {
            $this->makeRoot();
        }
    }

    /* ------------------------------------------------------------------------------------------------
     |  Check Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get whether node is root.
     *
     * @return bool
     */
    public function isRoot()
    {
        return is_null($this->getParentId());
    }

    /**
     * Get whether a node is a descendant of other node.
     *
     * @param self $other
     *
     * @return bool
     */
    public function isDescendantOf(self $other)
    {
        return (
            $this->getLft() > $other->getLft() &&
            $this->getLft() < $other->getRgt()
        );
    }

    /**
     * Get whether the node is immediate children of other node.
     *
     * @param  self  $other
     *
     * @return bool
     */
    public function isChildOf(self $other)
    {
        return $this->getParentId() == $other->getKey();
    }

    /**
     * Get whether the node is a sibling of another node.
     *
     * @param  self  $other
     *
     * @return bool
     */
    public function isSiblingOf(self $other)
    {
        return $this->getParentId() == $other->getParentId();
    }

    /**
     * Get whether the node is an ancestor of other node, including immediate parent.
     *
     * @param  self  $other
     *
     * @return bool
     */
    public function isAncestorOf(self $other)
    {
        return $other->isDescendantOf($this);
    }

    /**
     * Get whether the node has moved since last save.
     *
     * @return bool
     */
    public function hasMoved()
    {
        return $this->moved;
    }

    /**
     * Check if the model uses soft delete.
     *
     * @return bool
     */
    public static function usesSoftDelete()
    {
        static $softDelete;

        if (is_null($softDelete)) {
            $instance = new static;

            return $softDelete = method_exists($instance, 'withTrashed');
        }

        return $softDelete;
    }

    /**
     * Get whether user is intended to delete the model from database entirely.
     *
     * @return bool
     */
    protected function hardDeleting()
    {
        return ! $this->usesSoftDelete() || $this->forceDeleting;
    }

    /**
     * Assert that the node is not a descendant.
     *
     * @param  self  $node
     *
     * @return self
     */
    protected function assertNotDescendant(self $node)
    {
        if ($node == $this || $node->isDescendantOf($this)) {
            throw new LogicException('Node must not be a descendant.');
        }

        return $this;
    }

    /**
     * @param  self  $node
     *
     * @return self
     */
    protected function assertNodeExists(self $node)
    {
        if ( ! $node->getLft() || ! $node->getRgt()) {
            throw new LogicException('Node must exists.');
        }

        return $this;
    }
}
