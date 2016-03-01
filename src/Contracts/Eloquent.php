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
    /* ------------------------------------------------------------------------------------------------
     |  Getters & Setters
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey();

    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Save the model to the database.
     *
     * @param  array  $options
     *
     * @return bool
     */
    public function save(array $options = []);
}
