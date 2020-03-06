<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\WarningCategory as WarningCategoryEntity;
use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\WarningAction
 *
 * COLUMNS
 * @property int sv_post_node_id
 * @property int sv_post_thread_id
 * @property int sv_post_as_user_id
 * @property int sv_warning_category_id
 *
 * GETTERS
 * @property string title
 *
 * RELATIONS
 * @property WarningCategoryEntity Category
 * @property Forum PostForum
 * @property Thread PostThread
 * @property User PostAsUser
 */
class WarningAction extends XFCP_WarningAction
{
    /**
     * @return string|\XF\Phrase
     */
    public function getTitle()
    {
        if (is_callable('parent::getTitle'))
        {
            return parent::getTitle();
        }

        // getto as fuck
        return \XF::Phrase('points:') . ' '. $this->points;
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['action_length_type']['allowedValues'][] = 'hours';
        $structure->columns['sv_post_node_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['sv_post_thread_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['sv_post_as_user_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];
        $structure->columns['sv_warning_category_id'] = ['type' => self::UINT, 'default' => null, 'nullable' => true];

        $structure->relations['Category'] = [
            'entity'     => 'SV\WarningImprovements:WarningDefault',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_warning_category_id',
            'primary'    => true
        ];

        $structure->relations['PostForum'] = [
            'entity'     => 'XF:Forum',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_post_node_id',
            'primary'    => true
        ];

        $structure->relations['PostThread'] = [
            'entity'     => 'XF:Thread',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_post_thread_id',
            'primary'    => true
        ];

        $structure->relations['PostAsUser'] = [
            'entity'     => 'XF:User',
            'type'       => self::TO_ONE,
            'conditions' => 'sv_post_as_user_id',
            'primary'    => true
        ];

        if (!isset($structure->getters['title']))
        {
            $structure->getters['title'] = true;
        }

        return $structure;
    }
}
