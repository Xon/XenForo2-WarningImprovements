<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * @Extends \XF\Entity\UserOption
 *
 * @property int|null $sv_pending_warning_expiry
 */
class UserOption extends XFCP_UserOption
{
    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_pending_warning_expiry'] = ['type' => self::UINT, 'default' => null, 'nullable' => true, 'changeLog' => false];

        return $structure;
    }
}
