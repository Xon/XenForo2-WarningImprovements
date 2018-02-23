<?php

namespace SV\WarningImprovements\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class WarningCategory extends Repository
{
    public function getWarningCategoryRoots()
    {
        /** @var \SV\WarningImprovements\Finder\WarningCategory $finder */
        $finder = $this->finder('SV\WarningImprovements:WarningCategory');
        return $finder->rootOnly()->fetch();
    }
}