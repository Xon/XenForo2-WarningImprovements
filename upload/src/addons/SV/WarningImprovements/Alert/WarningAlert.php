<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;

class WarningAlert extends AbstractHandler
{
    public function canViewContent(Entity $entity, &$error = null)
    {
        return true;
    }
}