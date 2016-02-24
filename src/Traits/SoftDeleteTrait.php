<?php namespace Arcanedev\LaravelNestedSet\Traits;

/**
 * Class     SoftDeleteTrait
 *
 * @package  Arcanedev\LaravelNestedSet\Traits
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 *
 * @property  bool   $forceDeleting
 */
trait SoftDeleteTrait
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * @var \Carbon\Carbon
     */
    public static $deletedAt;

    /* ------------------------------------------------------------------------------------------------
     |  Functions
     | ------------------------------------------------------------------------------------------------
     */
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
}
