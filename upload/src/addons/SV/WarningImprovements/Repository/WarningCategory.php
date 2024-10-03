<?php

namespace SV\WarningImprovements\Repository;

use SV\WarningImprovements\Entity\WarningCategory as WarningCategoryEntity;
use SV\WarningImprovements\Finder\WarningCategory as WarningCategoryFinder;
use XF\Repository\AbstractCategoryTree;
use XF\Tree;
use function array_merge;

class WarningCategory extends AbstractCategoryTree
{
    protected function getClassName(): string
    {
        return 'SV\WarningImprovements:WarningCategory';
    }

    /**
     * @param null $categories
     * @param int  $rootId
     * @param bool $excludeEmpty
     * @return Tree
     */
    public function createCategoryTree($categories = null, $rootId = 0, bool $excludeEmpty = false): Tree
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

    public function mergeCategoryListExtras(array $extras, array $childExtras): array
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

    public function findCategoryParentList(WarningCategoryEntity $category, array $with = []): WarningCategoryFinder
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->findCategoryList(null, $with)
                    ->where('lft', '<', $category->lft)
                    ->where('rgt', '>', $category->rgt)
                    ->order('depth', 'DESC');
    }
}