<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\WarningCategory;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\WarningDefinition
 *
 * @property int sv_warning_category_id
 * @property WarningCategory Category
 */
class WarningDefinition extends XFCP_WarningDefinition
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_warning_category_id'] = ['type' => self::UINT, 'default' => 0];

        $structure->relations['Category'] = [
            'entity' => 'SV\WarningImprovements:WarningDefault',
            'type' => self::TO_ONE,
            'conditions' => 'sv_warning_category_id',
            'primary' => true
        ];

        return $structure;
    }
}
