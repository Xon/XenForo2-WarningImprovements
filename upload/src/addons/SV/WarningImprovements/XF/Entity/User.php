<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * GETTERS
 * @property \XF\Mvc\Entity\ArrayCollection warning_definitions
 * @property array warning_actions
 * @property int warning_actions_count
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

    public function getWarningDefinitions()
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->repository('XF:Warning');
        $warningDefinitions = $warningRepo->findWarningDefinitionsForList()
            ->with('Category')
            ->order('sv_display_order')
            ->fetch();

        return $warningDefinitions;
    }

    public function getUsableWarningDefinitions()
    {
        $warningDefinitions = $this->warning_definitions;

        $warningDefinitions = $warningDefinitions->filter(function ($warningDefinition)
        {
            /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $warningDefinition */
            return $warningDefinition->canView();
        });

        return $warningDefinitions->groupBy('sv_warning_category_id');
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

        $structure->getters['warning_definitions'] = true;
        $structure->getters['warning_actions'] = true;
        $structure->getters['warning_actions_count'] = true;

        return $structure;
    }
}
