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
        return ($this->warning_definition_id === 0 && $this->exists());
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

    public function getSpecificConversationContent(\XF\Entity\User $receiver, $contentType, \XF\Mvc\Entity\Entity $content, \XF\Entity\User $sender = null)
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $receiver */

        if (!$receiver->canViewIssuer())
        {
            if (empty($sender))
            {
                $sender = \XF::visitor();
            }

            $sender->username = \XF::phrase('WarningStaff')->render();
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
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_warning_category_id'] = ['type' => self::UINT, 'required' => 'sv_warning_improvements_please_select_valid_category', 'nullable' => true];
        $structure->columns['sv_display_order'] = ['type' => self::UINT];
        $structure->columns['sv_custom_title'] = ['type' => self::BOOL, 'default' => false];

        $structure->getters['is_custom'] = true;

        $structure->relations['Category'] = [
            'entity' => 'SV\WarningImprovements:WarningCategory',
            'type' => self::TO_ONE,
            'conditions' => 'sv_warning_category_id',
            'primary' => true
        ];

        return $structure;
    }
}
