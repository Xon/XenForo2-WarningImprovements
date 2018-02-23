<?php

namespace SV\WarningImprovements\Finder;

use XF\Mvc\Entity\Finder;

class WarningCategory extends Finder
{
    public function inParentWarningCategory(\SV\WarningImprovements\Entity\WarningCategory $warningCategory)
    {
        $this->where('parent_category_id', $warningCategory->parent_category_id);
        $this->order('display_order');

        return $this;
    }

    public function rootOnly()
    {
        $this->where('parent_category_id', '=', 0);
        $this->where('parent_category_id', '!=', null);

        return $this;
    }
}