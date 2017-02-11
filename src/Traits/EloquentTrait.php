<?php namespace Arcanedev\LaravelNestedSet\Traits;

use Arcanedev\LaravelNestedSet\Eloquent\Collection;
use Arcanedev\LaravelNestedSet\Eloquent\QueryBuilder;

/**
 * Class     EloquentTrait
 *
 * @package  Arcanedev\LaravelNestedSet\Traits
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
trait EloquentTrait
{
    /* -----------------------------------------------------------------
     |  Required Methods
     | -----------------------------------------------------------------
     */
    /**
     * Get the database connection for the model.
     *
     * @return \Illuminate\Database\Connection
     */
    abstract public function getConnection();

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    abstract public function getTable();

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    abstract public function getKey();

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    abstract public function getKeyName();

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     *
     * @return mixed
     */
    abstract public function getAttributeValue($key);

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array  $attributes
     * @param  bool   $sync
     *
     * @return self
     */
    abstract public function setRawAttributes(array $attributes, $sync = false);

    /**
     * Set the specific relationship in the model.
     *
     * @param  string  $relation
     * @param  mixed   $value
     *
     * @return self
     */
    abstract public function setRelation($relation, $value);

    /**
     * Get a relationship.
     *
     * @param  string  $key
     *
     * @return mixed
     */
    abstract public function getRelationValue($key);

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     *
     * @return self
     */
    abstract public function newInstance($attributes = [], $exists = false);

    /**
     * Determine if the model or given attribute(s) have been modified.
     *
     * @param  array|string|null  $attributes
     *
     * @return bool
     */
    abstract public function isDirty($attributes = null);

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     *
     * @return self
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    abstract public function fill(array $attributes);

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     *
     * @return bool
     */
    abstract public function save(array $options = []);

    /**
     * Get a new query builder for the model's table.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract public function newQuery();

    /* -----------------------------------------------------------------
     |  Overrided Methods
     | -----------------------------------------------------------------
     */
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
}
