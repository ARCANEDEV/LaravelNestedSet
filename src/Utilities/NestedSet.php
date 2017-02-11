<?php namespace Arcanedev\LaravelNestedSet\Utilities;

use Arcanedev\LaravelNestedSet\NodeTrait;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class     NestedSet
 *
 * @package  Arcanedev\LaravelNestedSet\Utilities
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class NestedSet
{
    /* -----------------------------------------------------------------
     |  Constants
     | -----------------------------------------------------------------
     */
    /**
     * The name of default lft column.
     */
    const LFT       = '_lft';

    /**
     * The name of default rgt column.
     */
    const RGT       = '_rgt';

    /**
     * The name of default parent id column.
     */
    const PARENT_ID = 'parent_id';

    /**
     * Insert direction.
     */
    const BEFORE    = 1;

    /**
     * Insert direction.
     */
    const AFTER     = 2;

    /* -----------------------------------------------------------------
     |  Getters & Setters
     | -----------------------------------------------------------------
     */
    /**
     * Get a list of default columns.
     *
     * @return array
     */
    public static function getDefaultColumns()
    {
        return [self::LFT, self::RGT, self::PARENT_ID];
    }

    /* -----------------------------------------------------------------
     |  Migration Methods
     | -----------------------------------------------------------------
     */
    /**
     * Add default nested set columns to the table. Also create an index.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     */
    public static function columns(Blueprint $table)
    {
        $table->unsignedInteger(self::LFT);
        $table->unsignedInteger(self::RGT);
        $table->unsignedInteger(self::PARENT_ID)->nullable();

        $table->index(self::getDefaultColumns());
    }

    /**
     * Drop NestedSet columns.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     */
    public static function dropColumns(Blueprint $table)
    {
        $columns = self::getDefaultColumns();

        $table->dropIndex($columns);
        $table->dropColumn($columns);
    }

    /* -----------------------------------------------------------------
     |  Check Methods
     | -----------------------------------------------------------------
     */
    /**
     * Replaces instanceof calls for this trait.
     *
     * @param  mixed  $node
     *
     * @return bool
     */
    public static function isNode($node)
    {
        return is_object($node) && in_array(NodeTrait::class, (array) $node);
    }
}
