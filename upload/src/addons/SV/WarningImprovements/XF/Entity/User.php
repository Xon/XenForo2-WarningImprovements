<?php
/**
 * @noinspection PhpUnusedParameterInspection
 */

namespace SV\WarningImprovements\XF\Entity;

use SV\StandardLib\Helper;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Repository\UserChangeTemp as ExtendedUserChangeTempRepo;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\UserChangeTemp as UserChangeTempRepo;
use XF\Repository\Warning as WarningRepo;

/**
 * @extends \XF\Entity\User
 *
 * GETTERS
 *
 * @property-read AbstractCollection $warning_definitions
 * @property-read array $warning_actions
 * @property-read int $warning_actions_count
 */
class User extends XFCP_User
{
    public function getWarningDefaultContentAction(): string
    {
        switch (\XF::options()->sv_warningimprovements_default_content_action ?? '')
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

    /** @noinspection PhpMissingReturnTypeInspection */
    public function canViewWarnings()
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($visitor->user_id === Globals::$profileUserId && (\XF::app()->options()->sv_view_own_warnings ?? false))
        {
            return true;
        }

        return parent::canViewWarnings();
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canViewIssuer(&$error = null): bool
    {
        if (!$this->user_id)
        {
            return false;
        }

        return $this->hasPermission('general', 'viewWarning_issuer') || $this->hasPermission('general', 'viewWarning');
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canViewWarningActions(&$error = null): bool
    {
        $visitor = \XF::visitor();

        $error = \XF::phrase('requested_user_not_found');
        if (!$visitor->user_id)
        {
            return false;
        }

        if ($visitor->hasPermission('general', 'sv_viewWarningActions'))
        {
            $error = null;
            return true;
        }

        if ($visitor->user_id === $this->user_id && (\XF::options()->sv_view_own_warnings ?? false))
        {
            $error = null;
            return true;
        }

        return false;
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canViewNonSummaryWarningActions(&$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_showAllWarningActions');
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canViewDiscouragedWarningActions(&$error = null): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        $showDiscouragedWarningActions = \XF::options()->sv_show_discouraged_warning_actions ?? 3;

        switch ($showDiscouragedWarningActions)
        {
            case 0: // Admin/Mod/User
                return $visitor->is_admin || $visitor->is_moderator || ($this->user_id === $visitor->user_id);
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
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canEditWarningActions(&$error = ''): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_editWarningActions');
    }

    public function canBypassWarningTitleCensor(Phrase &$error = null) : bool
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'svBypassWarnTitleCensor');
    }

    public function getWarningDefinitions(): AbstractCollection
    {
        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = Helper::repository(WarningRepo::class);

        return $warningRepo->findWarningDefinitionsForList()
                           ->with('Category')
                           ->order('sv_display_order')
                           ->fetch();
    }

    public function getUsableWarningDefinitions(): array
    {
        $warningDefinitions = $this->warning_definitions;

        $warningDefinitions = $warningDefinitions->filter(function ($warningDefinition) {
            /** @var WarningDefinition $warningDefinition */
            return $warningDefinition->canView();
        });

        return $warningDefinitions->groupBy('sv_warning_category_id');
    }

    public function getWarningActions(): AbstractCollection
    {
        /** @var ExtendedUserChangeTempRepo $userChangeTempRepo */
        $userChangeTempRepo = Helper::repository(UserChangeTempRepo::class);

        return $userChangeTempRepo->getWarningActions(
            $this->user_id,
            $this->canViewNonSummaryWarningActions(),
            $this->canViewDiscouragedWarningActions()
        )->fetch();
    }

    public function getWarningActionsCount(): int
    {
        /** @var ExtendedUserChangeTempRepo $userChangeTempRepo */
        $userChangeTempRepo = Helper::repository(UserChangeTempRepo::class);

        return $userChangeTempRepo->countWarningActions(
            $this,
            $this->canViewNonSummaryWarningActions(),
            $this->canViewDiscouragedWarningActions()
        );
    }

    public function rebuildWarningPoints()
    {
        parent::rebuildWarningPoints();

        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = Helper::repository(WarningRepo::class);
        $warningRepo->updatePendingExpiryForLater($this, true);
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['warning_definitions'] = ['getter' => 'getWarningDefinitions', 'cache' => true];
        $structure->getters['warning_actions'] = ['getter' => 'getWarningActions', 'cache' => true];
        $structure->getters['warning_actions_count'] = ['getter' => 'getWarningActionsCount', 'cache' => true];

        return $structure;
    }
}