<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\User
 */
class User extends XFCP_User
{
    public function canViewWarnings()
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($visitor->user_id === \SV\WarningImprovements\Listener::$profileUserId && \XF::app()->options()->sv_view_own_warnings)
        {
            return true;
        }

        return parent::canViewWarnings();
    }

    public function canViewIssuer()
    {
        if (!$this->user_id)
        {
            return false;
        }

        return $this->hasPermission('general', 'viewWarning_issuer');
    }

    public function canViewWarningActions(&$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($visitor->user_id == $this->user_id)
        {
            return \XF::options()->sv_view_own_warnings;
        }

        return $visitor->hasPermission('general', 'sv_viewWarningActions');
    }

    public function canViewNonSummaryWarningActions(&$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_showAllWarningActions');
    }

    public function canViewDiscouragedWarningActions(&$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        $showDiscouragedWarningActions = \XF::options()->sv_show_discouraged_warning_actions;

        switch ($showDiscouragedWarningActions)
        {
            case 0: // Admin/Mod/User
                return $visitor->is_admin || $visitor->is_moderator || ($this->user_id == $visitor->user_id);
            case 1: // Admin/Mod
                return $visitor->is_admin || $visitor->is_moderator;
            case 2: // Admin
                return $visitor->is_admin;
            case 3:
            default: // None
                return false;
        }
    }

    public function getWarningActions()
    {
        /** @var \SV\WarningImprovements\XF\Repository\UserChangeTemp $userChangeTempRepo */
        $userChangeTempRepo = $this->repository('XF:UserChangeTemp');

        return $userChangeTempRepo->getWarningActions(
            $this,
            $this->canViewNonSummaryWarningActions(),
            $this->canViewDiscouragedWarningActions()
        );
    }

    public function getWarningActionsCount()
    {
        /** @var \SV\WarningImprovements\XF\Repository\UserChangeTemp $userChangeTempRepo */
        $userChangeTempRepo = $this->repository('XF:UserChangeTemp');

        return $userChangeTempRepo->countWarningActions(
            $this,
            $this->canViewNonSummaryWarningActions(),
            $this->canViewDiscouragedWarningActions()
        );
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['warning_actions'] = true;
        $structure->getters['warning_actions_count'] = true;
    
        return $structure;
    }
}