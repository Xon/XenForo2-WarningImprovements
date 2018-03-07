<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\UserOption
 *
 * @property int sv_pending_warning_expiry
 */
class UserOption extends XFCP_UserOption
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_pending_warning_expiry'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];

        return $structure;
    }
}
