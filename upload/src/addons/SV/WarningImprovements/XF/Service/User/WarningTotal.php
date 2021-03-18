<?php

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Entity\WarningCategory;

class WarningTotal
{
    /** @var WarningTotal */
    public $parent;
    /** @var WarningCategory */
    public $category;
    /** @var int */
    public $newCount;
    /** @var int */
    public $newPoints;
    /** @var int */
    public $oldCount;
    /** @var int */
    public $oldPoints;

    public function __construct(WarningCategory $category, int $newPoints = 0, int $newCount = 0, int $oldPoints = 0, int $oldCount = 0)
    {
        $this->category = $category;
        $this->newPoints = $newPoints;
        $this->newCount = $newCount;
        $this->oldPoints = $oldPoints;
        $this->oldCount = $oldCount;
    }

    public function addOld(int $oldPoints)
    {
        $this->oldPoints += $oldPoints;
        $this->oldCount += 1;
        if ($this->parent)
        {
            $this->parent->addOld($oldPoints);
        }
    }

    public function addNew(int $newPoints)
    {
        $this->newPoints += $newPoints;
        $this->newCount += 1;
        if ($this->parent)
        {
            $this->parent->addNew($newPoints);
        }
    }
}