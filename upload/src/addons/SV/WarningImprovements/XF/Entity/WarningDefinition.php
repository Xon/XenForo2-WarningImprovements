<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Entity;

use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int sv_warning_category_id
 * @property int sv_display_order
 * @property bool sv_custom_title
 * @property bool is_custom
 * @property string custom_title_placeholder
 *
 * RELATIONS
 * @property \SV\WarningImprovements\Entity\WarningCategory Category
 */
class WarningDefinition extends XFCP_WarningDefinition
{
    /**
     * @param string|null $error
     * @return bool
     */
    public function canView(&$error = null)
    {
        $visitor = \XF::visitor();

        if (empty($visitor->user_id))
        {
            return false;
        }

        return $this->isUsable($error);
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function isUsable(&$error = null)
    {
        if ($this->Category && $this->Category->is_usable)
        {
            return true;
        }

        $error = \XF::phrase('sv_no_permission_to_give_warning');

        return false;
    }

    /**
     * @return bool
     */
    public function getIsCustom()
    {
        return ($this->warning_definition_id === 0 && $this->exists());
    }

    /**
     * @return string|\XF\Phrase
     */
    public function getCustomTitlePlaceholder()
    {
        return ($this->is_custom) ? '' : $this->title;
    }

    public function verifySvWarningCategoryId($sv_warning_category_id)
    {
        if (empty($sv_warning_category_id))
        {
            $this->error(\XF::phrase('sv_warning_improvements_please_select_valid_category'));

            return false;
        }

        return true;
    }

    /**
     * @param UserEntity      $receiver
     * @param string          $contentType
     * @param Entity          $content
     * @param UserEntity|null $sender
     * @return array
     */
    public function getSpecificConversationContent(UserEntity $receiver, $contentType, Entity $content, UserEntity $sender = null)
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $receiver */

        if (!$receiver->canViewIssuer())
        {
            if (empty($sender))
            {
                $sender = \XF::visitor();
            }

            $sender->username = \XF::phrase('WarningStaff')->render();

            if (!empty($anonymizeAsUserId = $this->app()->options()->sv_warningimprovements_warning_user))
            {
                if ($warningStaff = $this->em()->find('XF:User', $anonymizeAsUserId))
                {
                    $sender = $warningStaff;
                }
            }
        }

        return parent::getSpecificConversationContent($receiver, $contentType, $content, $sender);
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->Category)
        {
            $this->Category->warningAdded($this);
            $this->Category->save(false);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->Category)
        {
            $this->Category->warningRemoved($this);
            $this->Category->save(false);
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['expiry_type']['allowedValues'][] = 'hours';
        $structure->columns['sv_warning_category_id'] = ['type' => self::UINT, 'required' => 'sv_warning_improvements_please_select_valid_category', 'nullable' => true];
        $structure->columns['sv_display_order'] = ['type' => self::UINT];
        $structure->columns['sv_custom_title'] = ['type' => self::BOOL, 'default' => false];

        $structure->getters['is_custom'] = true;
        $structure->getters['custom_title_placeholder'] = true;

        $structure->relations['Category'] = [
            'entity'     => 'SV\WarningImprovements:WarningCategory',
            'type'       => self::TO_ONE,
            'conditions' => [['warning_category_id', '=', '$sv_warning_category_id']],
            'primary'    => true
        ];

        return $structure;
    }
}
