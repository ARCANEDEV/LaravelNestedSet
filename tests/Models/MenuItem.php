<?php namespace Arcanedev\LaravelNestedSet\Tests\Models;

use Arcanedev\LaravelNestedSet\Traits\NodeTrait;
use Arcanedev\LaravelNestedSet\Utilities\NestedSet;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    /* ------------------------------------------------------------------------------------------------
     |  Constants
     | ------------------------------------------------------------------------------------------------
     */
    const LFT       = NestedSet::LFT;
    const RGT       = NestedSet::RGT;
    const PARENT_ID = NestedSet::PARENT_ID;

    /* ------------------------------------------------------------------------------------------------
     |  Traits
     | ------------------------------------------------------------------------------------------------
     */
    use NodeTrait;

    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    public $timestamps = false;

    protected $fillable = ['menu_id'];

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    public static function resetActionsPerformed()
    {
        static::$actionsPerformed = 0;
    }

    protected function getScopeAttributes()
    {
        return ['menu_id'];
    }

    /**
     * @param self $parent
     *
     * @return $this
     *
     * @deprecated since 4.1
     */
    public function appendTo(self $parent)
    {
        return $this->appendToNode($parent);
    }

    /**
     * @param self $parent
     *
     * @return $this
     *
     * @deprecated since 4.1
     */
    public function prependTo(self $parent)
    {
        return $this->prependToNode($parent);
    }

    /**
     * @param self $node
     *
     * @return bool
     *
     * @deprecated since 4.1
     */
    public function insertBefore(self $node)
    {
        return $this->insertBeforeNode($node);
    }
    /**
     * @param  self  $node
     *
     * @return bool
     *
     * @deprecated since 4.1
     */
    public function insertAfter(self $node)
    {
        return $this->insertAfterNode($node);
    }
    /**
     * @param array $columns
     *
     * @return self|null
     *
     * @deprecated since 4.1
     */
    public function getNext(array $columns = [ '*' ])
    {
        return $this->getNextNode($columns);
    }
    /**
     * @param array $columns
     *
     * @return self|null
     *
     * @deprecated since 4.1
     */
    public function getPrev(array $columns = [ '*' ])
    {
        return $this->getPrevNode($columns);
    }
    /**
     * @return string
     */
    public function getParentIdName()
    {
        return static::PARENT_ID;
    }
    /**
     * @return string
     */
    public function getLftName()
    {
        return static::LFT;
    }
    /**
     * @return string
     */
    public function getRgtName()
    {
        return static::RGT;
    }
}
