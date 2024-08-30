<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\UserOption
 *
 * @property int|null $sv_pending_warning_expiry
 * @property string|null $sv_warning_view_type
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
        $structure->columns['sv_warning_view'] = ['type' => self::STR, 'default' => null, 'allowedValues' => ['radio', 'select'], 'nullable' => true];

        return $structure;
    }
}
