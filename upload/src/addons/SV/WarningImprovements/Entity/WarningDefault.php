<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null warning_default_id
 * @property int threshold_points
 * @property string expiry_type
 * @property int expiry_extension
 * @property bool active
 */
class WarningDefault extends Entity
{
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
            'warning_default_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'threshold_points'   => ['type' => self::UINT, 'required' => true, 'max' => 65535],
            'expiry_type'        => [
                'type'          => self::STR, 'default' => 'never',
                'allowedValues' => ['never', 'days', 'weeks', 'months', 'years']
            ],
            'expiry_extension'   => ['type' => self::UINT, 'default' => 0, 'max' => 65535],
            'active'             => ['type' => self::BOOL, 'required' => true],
        ];
        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}
