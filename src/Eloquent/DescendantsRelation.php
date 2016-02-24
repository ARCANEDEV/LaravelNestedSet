<?php namespace Arcanedev\LaravelNestedSet\Eloquent;

use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Arcanedev\LaravelNestedSet\NodeTrait;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Class     DescendantsRelation
 *
 * @package  Arcanedev\Taxonomies
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class DescendantsRelation extends Relation
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * The Eloquent query builder instance.
     *
     * @var \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder
     */
    protected $query;

    /**
     * The parent model instance.
     *
     * @var \Arcanedev\LaravelNestedSet\NodeTrait
     */
    protected $parent;

    /* ------------------------------------------------------------------------------------------------
     |  Constructor
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * DescendantsRelation constructor.
     *
     * @param  \Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder  $builder
     * @param  \Illuminate\Database\Eloquent\Model|NodeTrait      $model
     */
    public function __construct(QueryBuilder $builder, Model $model)
    {
        if ( ! NestedSet::isNode($model)) {
            throw new InvalidArgumentException('Model must be node.');
        }

        parent::__construct($builder, $model);
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parent
     * @param  array|mixed                            $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationQuery(
        EloquentBuilder $query,
        EloquentBuilder $parent,
        $columns = ['*']
    ) {
        $query->select($columns);

        $table = $query->getModel()->getTable();
        $hash  = $this->getRelationCountHash();

        $query->from("$table as $hash");

        $table = $this->wrap($table);
        $hash  = $this->wrap($hash);
        $lft   = $this->wrap($this->parent->getLftName());
        $rgt   = $this->wrap($this->parent->getRgtName());

        return $query->whereRaw("{$hash}.{$lft} between {$table}.{$lft} + 1 and {$table}.{$rgt}");
    }

    /**
     * Get a relationship join table hash.
     *
     * @return string
     */
    public function getRelationCountHash()
    {
        return 'self_'.md5(microtime(true));
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if ( ! static::$constraints) {
            return;
        }

        $this->query->whereDescendantOf($this->parent);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->whereNested(function (Builder $inner) use ($models) {
            // We will use this query in order to apply constraints to the base query builder
            $outer = $this->parent->newQuery();

            foreach ($models as $model) {
                $outer->setQuery($inner)->orWhereDescendantOf($model);
            }
        });
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
            /** @var Model $model */
            $model->setRelation(
                $relation,
                $this->getDescendantsForModel($model, $results)
            );
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
     * @param  \Illuminate\Database\Eloquent\Model       $model
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    protected function getDescendantsForModel(Model $model, EloquentCollection $results)
    {
        $result = $this->related->newCollection();

        foreach ($results as $descendant) {
            if ($descendant->isDescendantOf($model)) {
                $result->push($descendant);
            }
        }

        return $result;
    }
}
