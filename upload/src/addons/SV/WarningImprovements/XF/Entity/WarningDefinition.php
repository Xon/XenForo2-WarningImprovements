<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\WarningCategory;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int sv_warning_category_id
 * @property int sv_display_order
 * @property bool sv_custom_title
 *
 * RELATIONS
 * @property \SV\WarningImprovements\Entity\WarningCategory Category
 */
class WarningDefinition extends XFCP_WarningDefinition
{
    public function getIsCustom()
    {
        return ($this->warning_definition_id === 0);
    }

    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_warning_category_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['sv_display_order'] = ['type' => self::UINT];
        $structure->columns['sv_custom_title'] = ['type' => self::BOOL, 'default' => false];

        $structure->getters['is_custom'] = true;

        $structure->relations['Category'] = [
            'entity' => 'SV\WarningImprovements:WarningDefault',
            'type' => self::TO_ONE,
            'conditions' => 'sv_warning_category_id',
            'primary' => true
        ];

        return $structure;
    }
}
