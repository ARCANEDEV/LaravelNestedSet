<?php namespace Arcanedev\LaravelNestedSet\Eloquent;

use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class AncestorsRelation
 *
 * @package  Arcanedev\LaravelNestedSet\Eloquent
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class AncestorsRelation extends Relation
{
    /* -----------------------------------------------------------------
     |  Properties
     | -----------------------------------------------------------------
     */
    /**
     * @var \Arcanedev\LaravelNestedSet\NodeTrait|\Illuminate\Database\Eloquent\Model
     */
    protected $parent;

    /**
     * @var QueryBuilder
     */
    protected $query;

    /* -----------------------------------------------------------------
     |  Constructor
     | -----------------------------------------------------------------
     */
    /**
     * AncestorsRelation constructor.
     *
     * @param  \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder  $builder
     * @param  \Illuminate\Database\Eloquent\Model|mixed          $model
     */
    public function __construct(QueryBuilder $builder, Model $model)
    {
        if ( ! NestedSet::isNode($model))
            throw new InvalidArgumentException('Model must be node.');

        parent::__construct($builder, $model);
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceCountQuery(EloquentBuilder $query, EloquentBuilder $parentQuery)
    {
        throw new RuntimeException('Cannot count ancestors, use depth functionality instead');
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed                            $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(EloquentBuilder $query, EloquentBuilder $parentQuery, $columns = [ '*' ])
    {
        $query->select($columns);

        $table = $query->getModel()->getTable();

        $query->from($table.' as '.$hash = $this->getRelationSubSelectHash());

        $grammar = $query->getQuery()->getGrammar();
        $table   = $grammar->wrapTable($table);
        $hash    = $grammar->wrapTable($hash);

        $parentIdName = $grammar->wrap($this->parent->getParentIdName());

        return $query->whereRaw("{$hash}.{$parentIdName} = {$table}.{$parentIdName}");
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed                            $columns
     *
     * @return mixed
     */
    public function getRelationQuery(EloquentBuilder $query, EloquentBuilder $parentQuery, $columns = [ '*' ])
    {
        return $this->getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
     * Get a relationship join table hash.
     *
     * @return string
     */
    public function getRelationSubSelectHash()
    {
        return 'self_'.md5(microtime(true));
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if ( ! static::$constraints) return;

        $this->query->whereAncestorOf($this->parent)->defaultOrder();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     */
    public function addEagerConstraints(array $models)
    {
        $model = $this->query->getModel();
        $table = $model->getTable();
        $key   = $model->getKeyName();

        $grammar = $this->query->getQuery()->getGrammar();
        $table   = $grammar->wrapTable($table);
        $hash    = $grammar->wrap($this->getRelationSubSelectHash());
        $key     = $grammar->wrap($key);
        $lft     = $grammar->wrap($this->parent->getLftName());
        $rgt     = $grammar->wrap($this->parent->getRgtName());

        $sql = "$key IN (SELECT DISTINCT($key) FROM {$table} INNER JOIN "
            . "(SELECT {$lft}, {$rgt} FROM {$table} WHERE {$key} IN (" . implode(',', $this->getKeys($models))
            . ")) AS $hash ON {$table}.{$lft} <= {$hash}.{$lft} AND {$table}.{$rgt} >= {$hash}.{$rgt})";

        $this->query
            ->whereNested(function (Builder $inner) use ($sql) {
                $inner->whereRaw($sql);
            })
            ->orderBy('lft', 'ASC');
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     *
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array                                     $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string                                    $relation
     *
     * @return array
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getAncestorsForModel($model, $results));
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->get();
    }

    /**
     * Get ancestors for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model       $model
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getAncestorsForModel(Model $model, EloquentCollection $results)
    {
        $result = $this->related->newCollection();

        foreach ($results as $ancestor) {
            if ($ancestor->isAncestorOf($model))
                $result->push($ancestor);
        }

        return $result;
    }
}
