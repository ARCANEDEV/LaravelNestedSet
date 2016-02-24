<?php namespace Arcanedev\LaravelNestedSet\Eloquent;

use Arcanedev\LaravelNestedSet\Traits\NodeTrait;
use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Arcanedev\LaravelNestedSet\Utilities\TreeHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Expression;
use LogicException;

/**
 * Class     QueryBuilder
 *
 * @package  Arcanedev\LaravelNestedSet\Eloquent
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class QueryBuilder extends Builder
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * The model being queried.
     *
     * @var \Arcanedev\LaravelNestedSet\Traits\NodeTrait
     */
    protected $model;

    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get node's `lft` and `rgt` values.
     *
     * @param  mixed  $id
     * @param  bool   $required
     *
     * @return array
     */
    public function getNodeData($id, $required = false)
    {
        $query = $this->toBase();

        $query->where($this->model->getKeyName(), '=', $id);

        $data  = $query->first([
            $this->model->getLftName(),
            $this->model->getRgtName(),
        ]);

        if ( ! $data && $required) {
            throw new ModelNotFoundException;
        }

        return (array) $data;
    }

    /**
     * Get plain node data.
     *
     * @param  mixed  $id
     * @param  bool   $required
     *
     * @return array
     */
    public function getPlainNodeData($id, $required = false)
    {
        return array_values($this->getNodeData($id, $required));
    }

    /**
     * Scope limits query to select just root node.
     *
     * @return self
     */
    public function whereIsRoot()
    {
        $this->query->whereNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * Limit results to ancestors of specified node.
     *
     * @param  mixed  $id
     *
     * @return self
     */
    public function whereAncestorOf($id)
    {
        $keyName = $this->model->getKeyName();

        if (NestedSet::isNode($id)) {
            $value = '?';

            $this->query->addBinding($id->getLft());

            $id = $id->getKey();
        } else {
            $valueQuery = $this->model
                ->newQuery()
                ->toBase()
                ->select("_.".$this->model->getLftName())
                ->from($this->model->getTable().' as _')
                ->where($keyName, '=', $id)
                ->limit(1);

            $this->query->mergeBindings($valueQuery);

            $value = '(' . $valueQuery->toSql() . ')';
        }

        list($lft, $rgt) = $this->wrappedColumns();

        $this->query->whereRaw("{$value} between {$lft} and {$rgt}");

        // Exclude the node
        $this->where($keyName, '<>', $id);

        return $this;
    }

    /**
     * Get ancestors of specified node.
     *
     * @param  mixed  $id
     * @param  array  $columns
     *
     * @return self
     */
    public function ancestorsOf($id, array $columns = ['*'])
    {
        return $this->whereAncestorOf($id)->get($columns);
    }

    /**
     * Add node selection statement between specified range.
     *
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool    $not
     *
     * @return self
     */
    public function whereNodeBetween($values, $boolean = 'and', $not = false)
    {
        $this->query->whereBetween($this->model->getLftName(), $values, $boolean, $not);

        return $this;
    }

    /**
     * Add node selection statement between specified range joined with `or` operator.
     *
     * @param  array  $values
     *
     * @return self
     */
    public function orWhereNodeBetween($values)
    {
        return $this->whereNodeBetween($values, 'or');
    }

    /**
     * Add constraint statement to descendants of specified node.
     *
     * @param  mixed   $id
     * @param  string  $boolean
     * @param  bool    $not
     *
     * @return self
     */
    public function whereDescendantOf($id, $boolean = 'and', $not = false)
    {
        $data = NestedSet::isNode($id)
            ? $id->getBounds()
            : $this->model->newNestedSetQuery()->getPlainNodeData($id, true);

        // Don't include the node
        ++$data[0];

        return $this->whereNodeBetween($data, $boolean, $not);
    }

    /**
     * @param  mixed  $id
     *
     * @return self
     */
    public function whereNotDescendantOf($id)
    {
        return $this->whereDescendantOf($id, 'and', true);
    }

    /**
     * @param  mixed  $id
     *
     * @return self
     */
    public function orWhereDescendantOf($id)
    {
        return $this->whereDescendantOf($id, 'or');
    }

    /**
     * @param  mixed  $id
     *
     * @return self
     */
    public function orWhereNotDescendantOf($id)
    {
        return $this->whereDescendantOf($id, 'or', true);
    }

    /**
     * Get descendants of specified node.
     *
     * @param  mixed  $id
     * @param  array  $columns
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function descendantsOf($id, array $columns = ['*'])
    {
        try {
            return $this->whereDescendantOf($id)->get($columns);
        }
        catch (ModelNotFoundException $e) {
            return $this->model->newCollection();
        }
    }

    /**
     * @param  mixed   $id
     * @param  string  $operator
     * @param  string  $boolean
     *
     * @return self
     */
    protected function whereIsBeforeOrAfter($id, $operator, $boolean)
    {
        if (NestedSet::isNode($id)) {
            $value = '?';

            $this->query->addBinding($id->getLft());
        } else {
            $valueQuery = $this->model
                ->newQuery()
                ->toBase()
                ->select('_n.'.$this->model->getLftName())
                ->from($this->model->getTable().' as _n')
                ->where('_n.'.$this->model->getKeyName(), '=', $id);

            $this->query->mergeBindings($valueQuery);

            $value = '('.$valueQuery->toSql().')';
        }

        list($lft,) = $this->wrappedColumns();

        $this->query->whereRaw("{$lft} {$operator} {$value}", [ ], $boolean);

        return $this;
    }

    /**
     * Constraint nodes to those that are after specified node.
     *
     * @param  mixed   $id
     * @param  string  $boolean
     *
     * @return self
     */
    public function whereIsAfter($id, $boolean = 'and')
    {
        return $this->whereIsBeforeOrAfter($id, '>', $boolean);
    }

    /**
     * Constraint nodes to those that are before specified node.
     *
     * @param  mixed   $id
     * @param  string  $boolean
     *
     * @return self
     */
    public function whereIsBefore($id, $boolean = 'and')
    {
        return $this->whereIsBeforeOrAfter($id, '<', $boolean);
    }

    /**
     * Include depth level into the result.
     *
     * @param  string  $as
     *
     * @return self
     */
    public function withDepth($as = 'depth')
    {
        if ($this->query->columns === null) {
            $this->query->columns = ['*'];
        }

        $table = $this->wrappedTable();

        list($lft, $rgt) = $this->wrappedColumns();

        $query = $this->model
            ->newScopedQuery('_d')
            ->toBase()
            ->selectRaw('count(1) - 1')
            ->from($this->model->getTable().' as _d')
            ->whereRaw("{$table}.{$lft} between _d.{$lft} and _d.{$rgt}");

        $this->query->selectSub($query, $as);

        return $this;
    }

    /**
     * Get wrapped `lft` and `rgt` column names.
     *
     * @return array
     */
    protected function wrappedColumns()
    {
        $grammar = $this->query->getGrammar();

        return [
            $grammar->wrap($this->model->getLftName()),
            $grammar->wrap($this->model->getRgtName()),
        ];
    }

    /**
     * Get a wrapped table name.
     *
     * @return string
     */
    protected function wrappedTable()
    {
        return $this->query->getGrammar()->wrapTable($this->getQuery()->from);
    }

    /**
     * Wrap model's key name.
     *
     * @return string
     */
    protected function wrappedKey()
    {
        return $this->query->getGrammar()->wrap($this->model->getKeyName());
    }

    /**
     * Exclude root node from the result.
     *
     * @return self
     */
    public function withoutRoot()
    {
        $this->query->whereNotNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * Order by node position.
     *
     * @param  string  $dir
     *
     * @return self
     */
    public function defaultOrder($dir = 'asc')
    {
        $this->query->orders = null;

        $this->query->orderBy($this->model->getLftName(), $dir);

        return $this;
    }

    /**
     * Order by reversed node position.
     *
     * @return $this
     */
    public function reversed()
    {
        return $this->defaultOrder('desc');
    }

    /**
     * Move a node to the new position.
     *
     * @param  mixed  $key
     * @param  int    $position
     *
     * @return int
     */
    public function moveNode($key, $position)
    {
        list($lft, $rgt) = $this->model->newNestedSetQuery()
                                       ->getPlainNodeData($key, true);

        if ($lft < $position && $position <= $rgt) {
            throw new LogicException('Cannot move node into itself.');
        }

        // Get boundaries of nodes that should be moved to new position
        $from = min($lft, $position);
        $to   = max($rgt, $position - 1);

        // The height of node that is being moved
        $height   = $rgt - $lft + 1;

        // The distance that our node will travel to reach it's destination
        $distance = $to - $from + 1 - $height;

        // If no distance to travel, just return
        if ($distance === 0) {
            return 0;
        }

        if ($position > $lft) {
            $height *= -1;
        } else {
            $distance *= -1;
        }

        $boundary = [$from, $to];
        $query    = $this->toBase()->where(function (Query $inner) use ($boundary) {
            $inner->whereBetween($this->model->getLftName(), $boundary);
            $inner->orWhereBetween($this->model->getRgtName(), $boundary);
        });

        return $query->update($this->patch(
            compact('lft', 'rgt', 'from', 'to', 'height', 'distance')
        ));
    }

    /**
     * Make or remove gap in the tree. Negative height will remove gap.
     *
     * @param  int  $cut
     * @param  int  $height
     *
     * @return int
     */
    public function makeGap($cut, $height)
    {
        $query = $this->toBase()->whereNested(function (Query $inner) use ($cut) {
            $inner->where($this->model->getLftName(), '>=', $cut);
            $inner->orWhere($this->model->getRgtName(), '>=', $cut);
        });

        return $query->update($this->patch(
            compact('cut', 'height')
        ));
    }

    /**
     * Get patch for columns.
     *
     * @param  array  $params
     *
     * @return array
     */
    protected function patch(array $params)
    {
        $grammar = $this->query->getGrammar();
        $columns = [];

        foreach ([$this->model->getLftName(), $this->model->getRgtName()] as $col) {
            $columns[$col] = $this->columnPatch($grammar->wrap($col), $params);
        }

        return $columns;
    }

    /**
     * Get patch for single column.
     *
     * @param  string  $col
     * @param  array   $params
     *
     * @return string
     */
    protected function columnPatch($col, array $params)
    {
        /**
         * @var int $height
         * @var int $distance
         * @var int $lft
         * @var int $rgt
         * @var int $from
         * @var int $to
         */
        extract($params);

        if ($height > 0) $height = '+'.$height;

        if (isset($cut)) {
            return new Expression("case when {$col} >= {$cut} then {$col}{$height} else {$col} end");
        }

        if ($distance > 0) {
            $distance = '+'.$distance;
        }

        return new Expression(
            "case ".
            "when {$col} between {$lft} and {$rgt} then {$col}{$distance} ". // Move the node
            "when {$col} between {$from} and {$to} then {$col}{$height} ". // Move other nodes
            "else {$col} end"
        );
    }

    /**
     * Get statistics of errors of the tree.
     *
     * @return array
     */
    public function countErrors()
    {
        $checks = [
            'oddness'        => $this->getOddnessQuery(),      // Check if lft and rgt values are ok
            'duplicates'     => $this->getDuplicatesQuery(),   // Check if lft and rgt values are unique
            'wrong_parent'   => $this->getWrongParentQuery(),  // Check if parent_id is set correctly
            'missing_parent' => $this->getMissingParentQuery() // Check for nodes that have missing parent
        ];

        $query = $this->query->newQuery();

        foreach ($checks as $key => $inner) {
            /** @var \Illuminate\Database\Query\Builder $inner */
            $inner->selectRaw('count(1)');

            $query->selectSub($inner, $key);
        }

        return (array) $query->first();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getOddnessQuery()
    {
        return $this->model
            ->newNestedSetQuery()
            ->toBase()
            ->whereNested(function (Query $inner) {
                list($lft, $rgt) = $this->wrappedColumns();

                $inner->whereRaw("{$lft} >= {$rgt}")
                      ->orWhereRaw("({$rgt} - {$lft}) % 2 = 0");
            });
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getDuplicatesQuery()
    {
        $table = $this->wrappedTable();

        $query = $this->model
            ->newNestedSetQuery('c1')
            ->toBase()
            ->from($this->query->raw("{$table} c1, {$table} c2"))
            ->whereRaw("c1.id < c2.id")
            ->whereNested(function (Query $inner) {
                list($lft, $rgt) = $this->wrappedColumns();

                $inner->orWhereRaw("c1.{$lft}=c2.{$lft}")
                      ->orWhereRaw("c1.{$rgt}=c2.{$rgt}")
                      ->orWhereRaw("c1.{$lft}=c2.{$rgt}")
                      ->orWhereRaw("c1.{$rgt}=c2.{$lft}");
            });

        return $this->model->applyNestedSetScope($query, 'c2');
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getWrongParentQuery()
    {
        $table        = $this->wrappedTable();
        $keyName      = $this->wrappedKey();
        $parentIdName = $this->query->raw($this->model->getParentIdName());
        $query        = $this->model
            ->newNestedSetQuery('c')
            ->toBase()
            ->from($this->query->raw("{$table} c, {$table} p, $table m"))
            ->whereRaw("c.{$parentIdName}=p.{$keyName}")
            ->whereRaw("m.{$keyName} <> p.{$keyName}")
            ->whereRaw("m.{$keyName} <> c.{$keyName}")
            ->whereNested(function (Query $inner) {
                list($lft, $rgt) = $this->wrappedColumns();

                $inner->whereRaw("c.{$lft} not between p.{$lft} and p.{$rgt}")
                      ->orWhereRaw("c.{$lft} between m.{$lft} and m.{$rgt}")
                      ->whereRaw("m.{$lft} between p.{$lft} and p.{$rgt}");
            });

        $this->model->applyNestedSetScope($query, 'p');
        $this->model->applyNestedSetScope($query, 'm');

        return $query;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getMissingParentQuery()
    {
        return $this->model
            ->newNestedSetQuery()
            ->toBase()
            ->whereNested(function (Query $inner) {
                $table = $this->wrappedTable();
                $keyName = $this->wrappedKey();
                $parentIdName = $this->query->raw($this->model->getParentIdName());

                $existsCheck = $this->model
                    ->newNestedSetQuery()
                    ->toBase()
                    ->selectRaw('1')
                    ->from($this->query->raw("{$table} p"))
                    ->whereRaw("{$table}.{$parentIdName} = p.{$keyName}")
                    ->limit(1);

                $this->model->applyNestedSetScope($existsCheck, 'p');

                $inner->whereRaw("{$parentIdName} is not null")
                      ->addWhereExistsQuery($existsCheck, 'and', true);
            });
    }

    /**
     * Get the number of total errors of the tree.
     *
     * @return int
     */
    public function getTotalErrors()
    {
        return array_sum($this->countErrors());
    }

    /**
     * Get whether the tree is broken.
     *
     * @return bool
     */
    public function isBroken()
    {
        return $this->getTotalErrors() > 0;
    }

    /**
     * Fixes the tree based on parentage info.
     * Nodes with invalid parent are saved as roots.
     *
     * @return int The number of fixed nodes
     */
    public function fixTree()
    {
        $columns   = [
            $this->model->getKeyName(),
            $this->model->getParentIdName(),
            $this->model->getLftName(),
            $this->model->getRgtName(),
        ];

        $dictionary = $this->defaultOrder()
                ->get($columns)
                ->groupBy($this->model->getParentIdName())
                ->all();

        return TreeHelper::fixNodes($dictionary);
    }

    /**
     * Rebuild the tree based on raw data.
     * If item data does not contain primary key, new node will be created.
     *
     * @param  array  $data
     * @param  bool   $delete  Whether to delete nodes that exists but not in the data array
     *
     * @return int
     */
    public function rebuildTree(array $data, $delete = false)
    {
        $existing   = $this->get()->getDictionary();
        $dictionary = [];
        $this->buildRebuildDictionary($dictionary, $data, $existing);

        if ( ! empty($existing)) {
            if ($delete) {
                $this->model
                    ->newScopedQuery()
                    ->whereIn($this->model->getKeyName(), array_keys($existing))
                    ->forceDelete();
            } else {
                /** @var NodeTrait $model */
                foreach ($existing as $model) {
                    $dictionary[$model->getParentId()][] = $model;
                }
            }
        }

        return TreeHelper::fixNodes($dictionary);
    }

    /**
     * @param  array  $dictionary
     * @param  array  $data
     * @param  array  $existing
     * @param  mixed  $parentId
     */
    protected function buildRebuildDictionary(
        array &$dictionary,
        array $data,
        array &$existing,
        $parentId = null
    ) {
        $keyName = $this->model->getKeyName();

        foreach ($data as $itemData) {
            if ( ! isset($itemData[$keyName])) {
                $model = $this->model->newInstance();
                // We will save it as raw node since tree will be fixed
                $model->rawNode(0, 0, $parentId);
            } else {
                if ( ! isset($existing[$key = $itemData[$keyName]])) {
                    throw new ModelNotFoundException;
                }
                $model = $existing[$key];
                unset($existing[$key]);
            }

            $model->fill($itemData)->save();
            $dictionary[$parentId][] = $model;

            if ( ! isset($itemData['children'])) {
                continue;
            }

            $this->buildRebuildDictionary(
                $dictionary,
                $itemData['children'],
                $existing,
                $model->getKey()
            );
        }
    }

    /**
     * @param  string|null  $table
     *
     * @return self
     */
    public function applyNestedSetScope($table = null)
    {
        return $this->model->applyNestedSetScope($this, $table);
    }

    /**
     * Get the root node.
     *
     * @param  array  $columns
     *
     * @return self
     */
    public function root(array $columns = ['*'])
    {
        return $this->whereIsRoot()->first($columns);
    }
}
