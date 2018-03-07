<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\Repository;

use XF\Mvc\Entity\Finder;
use XF\Repository\AbstractCategoryTree;
use XF\Tree;

class WarningCategory extends AbstractCategoryTree
{
    /**
     * @return string
     */
    protected function getClassName()
    {
        return 'SV\WarningImprovements:WarningCategory';
    }

    /**
     * @param null $categories
     * @param int  $rootId
     * @param bool $excludeEmpty
     * @return Tree
     */
    public function createCategoryTree($categories = null, $rootId = 0, $excludeEmpty = false)
    {
        if ($excludeEmpty)
        {
            if ($categories === null)
            {
                $categories = $this->findCategoryList()
                                   ->where('warning_count', '<>', 0)
                                   ->fetch();
            }

            return new Tree($categories, 'parent_category_id', $rootId);
        }

        return parent::createCategoryTree($categories, $rootId);
    }

    /**
     * @param array $extras
     * @param array $childExtras
     * @return array
     */
    public function mergeCategoryListExtras(array $extras, array $childExtras)
    {
        $output = array_merge(
            [
                'warning_count' => 0,
                'childCount'    => 0
            ], $extras);

        foreach ($childExtras AS $child)
        {
            if (!empty($child['warning_count']))
            {
                $output['warning_count'] += $child['warning_count'];
            }

            $output['childCount'] += 1 + (!empty($child['childCount']) ? $child['childCount'] : 0);
        }

        return $output;
    }

    /**
     * @param \SV\WarningImprovements\Entity\WarningCategory|null $category
     * @param null                                                $with
     * @return Finder
     */
    public function findCategoryParentList(\SV\WarningImprovements\Entity\WarningCategory $category, $with = null)
    {
        return $this->findCategoryList(null, $with)
                    ->where('lft', '<', $category->lft)
                    ->where('rgt', '>', $category->rgt)
                    ->order('depth', 'DESC');
    }
}