<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\User
 */
class User extends XFCP_User
{
    public function canViewWarningActions(&$error = null, User $asUser = null)
    {
        $asUser = $asUser ?: \XF::visitor();

        if (!$asUser->user_id)
        {
            return false;
        }

        if ($asUser->user_id == $this->user_id)
        {
            return \XF::options()->sv_view_own_warnings;
        }

        return $asUser->hasPermission('general', 'sv_viewWarningActions');
    }

    public function canViewNonSummaryWarningActions(&$error = null, User $asUser = null)
    {
        $asUser = $asUser ?: \XF::visitor();

        if (!$asUser->user_id)
        {
            return false;
        }

        return $asUser->hasPermission('general', 'sv_showAllWarningActions');
    }

    public function canViewDiscouragedWarningActions(&$error = null, User $asUser = null)
    {
        $asUser = $asUser ?: \XF::visitor();

        if (!$asUser->user_id)
        {
            return false;
        }

        $showDiscouragedWarningActions = \XF::options()->sv_show_discouraged_warning_actions;

        switch ($showDiscouragedWarningActions)
        {
            case 0: // Admin/Mod/User
                return $asUser->is_admin || $asUser->is_moderator || ($this->user_id == $asUser->user_id);
            case 1: // Admin/Mod
                return $asUser->is_admin || $asUser->is_moderator;
            case 2: // Admin
                return $asUser->is_admin;
            case 3:
            default: // None
                return false;
        }
    }

    public function getWarningActionsCount()
    {
        return 0; // need to work on
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['warning_actions_count'] = true;
    
        return $structure;
    }
}