<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Entity\WarningCategory;

class WarningTotal
{
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

    public function __construct(WarningCategory $category, $newPoints = 0, $newCount = 0, $oldPoints = 0, $oldCount = 0)
    {
        $this->category = $category;
        $this->newPoints = $newPoints;
        $this->newCount = $newCount;
        $this->oldPoints = $oldPoints;
        $this->oldCount = $oldCount;
    }

    /**
     * @param int $oldPoints
     */
    public function addOld($oldPoints)
    {
        $this->oldPoints += $oldPoints;
        $this->oldCount += 1;
    }

    /**
     * @param int $newPoints
     */
    public function addNew($newPoints)
    {
        $this->newPoints += $newPoints;
        $this->newCount += 1;
    }

    /**
     * @param WarningTotal $warningTotal
     */
    public function addTotals(WarningTotal $warningTotal)
    {
        $this->oldPoints += $warningTotal->oldPoints;
        $this->oldCount += $warningTotal->oldCount;
        $this->newPoints += $warningTotal->newPoints;
        $this->newCount += $warningTotal->newCount;
    }
}