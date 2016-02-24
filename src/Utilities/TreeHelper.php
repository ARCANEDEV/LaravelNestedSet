<?php namespace Arcanedev\LaravelNestedSet\Utilities;

use Arcanedev\LaravelNestedSet\NodeTrait;

/**
 * Class     TreeHelper
 *
 * @package  Arcanedev\LaravelNestedSet\Utilities
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class TreeHelper
{
    /**
     * Fix nodes.
     *
     * @param  array  $dictionary
     *
     * @return int
     */
    public static function fixNodes(array &$dictionary)
    {
        $fixed = 0;
        $cut   = self::reorderNodes($dictionary, $fixed);

        // Save nodes that have invalid parent as roots
        while ( ! empty($dictionary)) {
            $dictionary[null] = reset($dictionary);
            unset($dictionary[key($dictionary)]);

            $cut = self::reorderNodes($dictionary, $fixed, null, $cut);
        }

        return $fixed;
    }

    /**
     * Reorder nodes.
     *
     * @param  array     $dictionary
     * @param  int       $fixed
     * @param  int|null  $parentId
     * @param  int       $cut
     *
     * @return int
     */
    protected static function reorderNodes(
        array &$dictionary,
        &$fixed,
        $parentId = null,
        $cut = 1
    ) {
        if ( ! isset($dictionary[$parentId])) {
            return $cut;
        }

        /** @var NodeTrait $model */
        foreach ($dictionary[$parentId] as $model) {
            $lft = $cut;
            $cut = self::reorderNodes($dictionary, $fixed, $model->getKey(), $cut + 1);
            $rgt = $cut;

            if ($model->rawNode($lft, $rgt, $parentId)->isDirty()) {
                $model->save();
                $fixed++;
            }

            ++$cut;
        }

        unset($dictionary[$parentId]);

        return $cut;
    }
}
