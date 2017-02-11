<?php namespace Arcanedev\LaravelNestedSet\Contracts;

/**
 * Interface  Eloquent
 *
 * @package   Arcanedev\LaravelNestedSet\Contracts
 * @author    ARCANEDEV <arcanedev.maroc@gmail.com>
 *
 * @see \Illuminate\Database\Eloquent\Model
 */
interface Eloquent
{
    /* -----------------------------------------------------------------
     |  Getters & Setters
     | -----------------------------------------------------------------
     */
    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey();

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName();

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable();

    /**
     * Get a relationship.
     *
     * @param  string  $key
     *
     * @return mixed
     */
    public function getRelationValue($key);

    /* -----------------------------------------------------------------
     |  Main Methods
     | -----------------------------------------------------------------
     */
    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     *
     * @return static
     */
    public function newInstance($attributes = [], $exists = false);

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     *
     * @return self
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes);

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     *
     * @return bool
     */
    public function save(array $options = []);

    /**
     * Get a new query builder for the model's table.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQuery();

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     *
     * @return \Arcanedev\LaravelNestedSet\Eloquent\Collection
     */
    public function newCollection(array $models = []);
}
