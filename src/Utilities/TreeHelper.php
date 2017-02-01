<?php namespace Arcanedev\LaravelNestedSet\Utilities;

use Arcanedev\LaravelNestedSet\Contracts\Nodeable;
use Arcanedev\LaravelNestedSet\NodeTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;

/**
 * Class     TreeHelper
 *
 * @package  Arcanedev\LaravelNestedSet\Utilities
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class TreeHelper
{
    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * @param  array                                           $data
     * @param  array                                           $existing
     * @param  \Arcanedev\LaravelNestedSet\Contracts\Nodeable  $model
     * @param  bool                                            $delete
     *
     * @return int
     */
    public static function rebuild(array $data, $existing, Nodeable $model, $delete = false)
    {
        $dictionary = [];

        self::rebuildDictionary($dictionary, $data, $existing, $model);

        if ( ! empty($existing)) {
            self::cleaningDictionary($dictionary, $existing, $model, $delete);
        }

        return self::fixNodes($dictionary);
    }

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

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Rebuild the dictionary.
     *
     * @param  array                                           $dictionary
     * @param  array                                           $data
     * @param  array                                           $existing
     * @param  \Arcanedev\LaravelNestedSet\Contracts\Nodeable  $model
     * @param  mixed                                           $parentId
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected static function rebuildDictionary(
        array &$dictionary,
        array $data,
        array &$existing,
        Nodeable &$model,
        $parentId = null
    ) {
        $keyName = $model->getKeyName();

        foreach ($data as $itemData) {
            $node = self::retrieveNode($existing, $model, $parentId, $itemData, $keyName);

            $node->fill(Arr::except($itemData, 'children'))->save();
            $dictionary[$parentId][] = $node;

            if (isset($itemData['children'])) {
                self::rebuildDictionary($dictionary, $itemData['children'], $existing, $model, $node->getKey());
            }
        }
    }

    /**
     * @param  array                                           $existing
     * @param  \Arcanedev\LaravelNestedSet\Contracts\Nodeable  $model
     * @param  mixed                                           $parentId
     * @param  array                                           $itemData
     * @param  mixed                                           $keyName
     *
     * @return \Arcanedev\LaravelNestedSet\Contracts\Nodeable
     */
    protected static function retrieveNode(array &$existing, Nodeable &$model, $parentId, $itemData, $keyName)
    {
        if ( ! isset($itemData[$keyName])) {
            // We will save it as raw node since tree will be fixed
            $node = $model->newInstance()->rawNode(0, 0, $parentId);
        }
        elseif ( ! isset($existing[ $key = $itemData[$keyName] ])) {
            throw new ModelNotFoundException;
        }
        else {
            $node = $existing[$key];
            unset($existing[$key]);
        }

        return $node;
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

    /**
     * Cleaning existing nodes.
     *
     * @param  array                                           $dictionary
     * @param  array                                           $existing
     * @param  \Arcanedev\LaravelNestedSet\Contracts\Nodeable  $model
     * @param  bool                                            $delete
     */
    private static function cleaningDictionary(&$dictionary, $existing, Nodeable $model, $delete)
    {
        if ($delete) {
            $model->newScopedQuery()
                  ->whereIn($model->getKeyName(), array_keys($existing))
                  ->forceDelete();

            return;
        }

        foreach ($existing as $node) {
            /** @var \Arcanedev\LaravelNestedSet\Contracts\Nodeable $node */
            $dictionary[ $node->getParentId() ][] = $node;
        }
    }
}
