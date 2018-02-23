<?php

namespace SV\WarningImprovements\Repository;

use XF\Mvc\Entity\Finder;
use XF\Repository\AbstractCategoryTree;

class WarningCategory extends AbstractCategoryTree
{
    protected function getClassName()
    {
        return 'SV\WarningImprovements:WarningCategory';
    }

    public function mergeCategoryListExtras(array $extras, array $childExtras)
    {
        $output = array_merge([
            'warning_count' => 0,
            'childCount' => 0
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

    public function getWarningCategoryRoots()
    {
        /** @var \SV\WarningImprovements\Finder\WarningCategory $finder */
        $finder = $this->finder('SV\WarningImprovements:WarningCategory');
        return $finder->rootOnly()->fetch();
    }
}