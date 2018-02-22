<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\UserChangeTemp
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    public function canViewDiscouragedWarningActions(&$error = '')
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
                return $visitor->is_admin || $visitor->is_moderator || ($this->User->user_id == $visitor->user_id);
            case 1: // Admin/Mod
                return $visitor->is_admin || $visitor->is_moderator;
            case 2: // Admin
                return $visitor->is_admin;
            case 3:
            default: // None
                return false;
        }
    }

    public function canViewNonSummaryWarningActions(&$error = '')
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_showAllWarningActions');
    }

    public function canViewWarningActions(&$error = '')
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($visitor->user_id == $this->User->user_id)
        {
            return \XF::options()->sv_view_own_warnings;
        }

        return $visitor->hasPermission('general', 'sv_viewWarningActions');
    }

    public function canEditWarningActions(&$error = '')
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_editWarningActions');
    }

    protected function _postSave()
    {
        parent::_postSave();

        $this->_getWarningRepo()->updatePendingExpiryFor($this->User, true);
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->_getWarningRepo()->updatePendingExpiryFor($this->User, true);
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\XF\Repository\Warning|\SV\WarningImprovements\XF\Repository\Warning
     */
    protected function _getWarningRepo()
    {
        return $this->repository('XF:Warning');
    }
}
