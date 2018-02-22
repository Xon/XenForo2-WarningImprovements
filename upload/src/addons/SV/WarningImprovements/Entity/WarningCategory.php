<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\Entity;

use SV\WarningImprovements\XF\Entity\WarningDefinition;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null warning_category_id
 * @property int|null parent_warning_category_id
 * @property int display_order
 * @property array allowed_user_group_ids
 *
 * RELATIONS
 * @property \SV\WarningImprovements\Entity\WarningDefault Parent
 * @property \SV\WarningImprovements\Entity\WarningDefault[] ChildCategories
 * @property \XF\Entity\WarningDefinition[] WarningDefinitions
 */
class WarningCategory extends Entity
{
    public function verifyParentWarningCategoryId()
    {
        throw new \LogicException('not implemented');
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_sv_warning_default';
        $structure->shortName = 'SV\WarningImprovements:WarningDefault';
        $structure->primaryKey = 'warning_default_id';
        $structure->columns = [
            'warning_category_id'        => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'parent_warning_category_id' => ['type' => self::UINT, 'nullable' => true],
            'display_order'              => ['type' => self::UINT, 'default' => 0],
            'allowed_user_group_ids'     => [
                'type' => self::LIST_COMMA, 'default' => [2],
                'list' => ['type' => 'posint', 'unique' => true, 'sort' => SORT_NUMERIC]
            ],

        ];
        $structure->getters = [];
        $structure->relations = [
            'Parent' => [
                'entity' => 'SV\WarningImprovements:WarningDefault',
                'type' => self::TO_ONE,
                'conditions' => [['warning_category_id', '=', '$parent_warning_category_id']],
                'primary' => true
            ],
            'ChildCategories' => [
                'entity' => 'SV\WarningImprovements:WarningDefault',
                'type' => self::TO_MANY,
                'conditions' => [['parent_warning_category_id', '=', '$warning_category_id']],
                'primary' => true
            ],
            'WarningDefinitions' => [
                'entity' => 'XF:WarningDefinition',
                'type' => self::TO_MANY,
                'conditions' => [['sv_warning_category_id', '=', '$warning_category_id']],
                'primary' => true
            ],
        ];

        return $structure;
    }
}
