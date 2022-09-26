<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Entity;

use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int sv_warning_category_id
 * @property int sv_display_order
 * @property bool sv_custom_title
 * @property bool is_custom
 * @property string custom_title_placeholder
 * @property bool $sv_spoiler_contents
 * @property bool $sv_disable_reactions
 *
 * GETTERS
 * @property \XF\Phrase $sv_content_spoiler_title
 *
 * RELATIONS
 * @property \SV\WarningImprovements\Entity\WarningCategory Category
 * @property \XF\Entity\Phrase $SvMasterContentSpoilerTitle
 */
class WarningDefinition extends XFCP_WarningDefinition
{
    const SV_CONTENT_SPOILER_TITLE = 'sv_warning_improvements_warning_spoiler_title';

    public function getSvContentSpoilerTitlePhraseName() : string
    {
        return static::SV_CONTENT_SPOILER_TITLE . '.' . $this->warning_definition_id;
    }

    public function getSvContentSpoilerTitle() : \XF\Phrase
    {
        return \XF::phrase($this->getSvContentSpoilerTitlePhraseName());
    }

    /**
     * @param Phrase|string|null $error
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
     * @param Phrase|string|null $error
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
     * @return string|Phrase
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
        /** @var User $receiver */

        if (!$receiver->canViewIssuer())
        {
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $sender = $userRepo->getGuestUser(\XF::phrase('WarningStaff')->render());

            $warningUserId = (int)($this->app()->options()->sv_warningimprovements_warning_user ?? 0);
            if ($warningUserId)
            {
                $warningStaff = $this->em()->find('XF:User', $warningUserId);
                if ($warningStaff)
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

    protected function _preDelete()
    {
        parent::_preDelete();

        if ($this->is_custom)
        {
            $this->error(\XF::phrase('sv_warning_improvements_custom_warning_cannot_be_deleted'));
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
        $structure->columns['sv_spoiler_contents'] = [
            'type' => self::BOOL,
            'default' => false
        ];
        $structure->columns['sv_disable_reactions'] = [
            'type' => self::BOOL,
            'default' => false
        ];

        $structure->getters['is_custom'] = true;
        $structure->getters['custom_title_placeholder'] = true;
        $structure->getters['sv_content_spoiler_title'] = true;

        $structure->relations['Category'] = [
            'entity'     => 'SV\WarningImprovements:WarningCategory',
            'type'       => self::TO_ONE,
            'conditions' => [['warning_category_id', '=', '$sv_warning_category_id']],
            'primary'    => true
        ];
        $structure->relations['SvMasterContentSpoilerTitle'] = [
            'entity' => 'XF:Phrase',
            'type' => self::TO_ONE,
            'conditions' => [
                ['language_id', '=', 0],
                ['title', '=', static::SV_CONTENT_SPOILER_TITLE . '.', '$warning_definition_id']
            ],
            'cascadeDelete' => true
        ];

        return $structure;
    }
}
