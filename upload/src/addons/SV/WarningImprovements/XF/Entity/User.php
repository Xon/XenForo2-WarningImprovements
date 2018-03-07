<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Globals;
use XF\Mvc\Entity\Structure;

/**
 * GETTERS
 * @property \XF\Mvc\Entity\ArrayCollection warning_definitions
 * @property array warning_actions
 * @property int warning_actions_count
 */
class User extends XFCP_User
{
    public function getWarningDefaultContentAction()
    {
        switch (\XF::options()->sv_warningimprovements_default_content_action)
        {
            case 'delete_content':
                return 'delete';
            case 'public_warning':
                return 'public';
            case 'none';
            default:
                return '';
        }
    }

    public function canViewWarnings()
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($visitor->user_id === Globals::$profileUserId && \XF::app()->options()->sv_view_own_warnings)
        {
            return true;
        }

        return parent::canViewWarnings();
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function canViewIssuer(/** @noinspection PhpUnusedParameterInspection */&$error = null)
    {
        if (!$this->user_id)
        {
            return false;
        }

        return $this->hasPermission('general', 'viewWarning_issuer') || $this->hasPermission('general', 'viewWarning');
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function canViewWarningActions(/** @noinspection PhpUnusedParameterInspection */&$error = null)
    {
        $visitor = \XF::visitor();

        $error = \XF::phrase('requested_user_not_found');

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

    /**
     * @param string|null $error
     * @return bool
     */
    public function canViewNonSummaryWarningActions(/** @noinspection PhpUnusedParameterInspection */&$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_showAllWarningActions');
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function canViewDiscouragedWarningActions(/** @noinspection PhpUnusedParameterInspection */&$error = null)
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

    /**
     * @param string $error
     * @return bool
     */
    public function canEditWarningActions(/** @noinspection PhpUnusedParameterInspection */&$error = '')
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_editWarningActions');
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
        )->fetch();
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