<?php namespace Arcanedev\LaravelNestedSet\Tests\Models;

use Arcanedev\LaravelNestedSet\NodeTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class     Category
 *
 * @package  Arcanedev\Taxonomies\Tests\Models
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class Category extends Model
{
    /* ------------------------------------------------------------------------------------------------
     |  Traits
     | ------------------------------------------------------------------------------------------------
     */
    use NodeTrait, SoftDeletes;

    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    protected $fillable = ['name', 'parent_id'];

    public $timestamps = false;

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    public static function resetActionsPerformed()
    {
        static::$actionsPerformed = 0;
    }
}
