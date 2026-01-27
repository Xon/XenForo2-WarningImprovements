<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\StandardLib\Helper;
use SV\WarningImprovements\XF\Repository\UserChangeTemp as ExtendedUserChangeTempRepo;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Entity\Phrase as PhraseEntity;
use XF\Entity\WarningAction as WarningActionEntity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\UserChangeTemp as UserChangeTempRepo;
use XF\Repository\Warning as WarningRepo;
use function implode;
use function is_array;
use function preg_match;
use function substr;

/**
 * @extends \XF\Entity\UserChangeTemp
 * @property-read PhraseEntity $name
 * @property-read PhraseEntity $result
 * @property bool              $is_expired
 * @property bool              $is_permanent
 * @property int               $effective_expiry_date
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    public function getName(): Phrase
    {
        $name = 'n_a';

        switch ($this->action_type)
        {
            case 'groups':
                $name = 'sv_warning_action_added_to_user_groups';
                break;

            case 'field':
                $name = 'discouraged';
                break;
        }

        return \XF::phrase($name);
    }

    public function getResult(): Phrase
    {
        switch ($this->action_type)
        {
            case 'groups':
                $result = 'n_a';

                if (substr($this->action_modifier, 0, 15) === 'warning_action_')
                {
                    $userGroupNames = [];

                    /** @var ExtendedUserChangeTempRepo $userGroupRepo */
                    $userGroupRepo = Helper::repository(UserChangeTempRepo::class);
                    $userGroups = $userGroupRepo->getCachedUserGroupsList();
                    $userGroupChangeSet = $userGroupRepo->getCachedUserGroupChangeList($this->user_id);

                    $userGroupChanges = $userGroupChangeSet[$this->action_modifier] ?? null;
                    if (is_array($userGroupChanges))
                    {
                        foreach ($userGroupChangeSet[$this->action_modifier] as $userGroupId)
                        {
                            if (!empty($userGroups[$userGroupId]))
                            {
                                $userGroupNames[] = $userGroups[$userGroupId]->title;
                            }
                        }
                    }

                    if (!empty($userGroupNames))
                    {
                        $result = implode(',', $userGroupNames);
                    }
                }

                break;

            case 'field':
                $result = ($this->new_value === '1') ? 'yes' : 'no';
                break;
            default:
                $result = 'n_a';
                break;
        }

        return \XF::phrase($result);
    }

    public function getIsExpired(): bool
    {
        return ($this->expiry_date <= \XF::$time && !$this->is_permanent);
    }

    public function getIsPermanent(): bool
    {
        return ($this->effective_expiry_date === null);
    }

    public function getEffectiveExpiryDate(): ?int
    {
        $visitor = \XF::visitor();

        $effectiveExpiryDate = $this->expiry_date;
        // need to check how this expires
        if ($effectiveExpiryDate === null && preg_match('#^warning_action_(\d+)$#', $this->action_modifier, $matches))
        {
            $warningActionId = $matches[1];
            /** @var WarningAction $warningAction */
            $warningAction = Helper::find(WarningActionEntity::class, $warningActionId);
            if ($warningAction && $warningAction->action_length_type === 'points')
            {
                // compute when the minimum level of points expire.
                $effectiveExpiryDate = \XF::db()->fetchOne(
                    'SELECT expiry_date
                            FROM
                            (
                                SELECT @pointSum := @pointSum+ points AS pointSum, permanent, expiry_date 
                                FROM
                                (
                                    SELECT points, IF(expiry_date = 0, 1, 0) AS permanent, expiry_date 
                                    FROM xf_warning 
                                    WHERE user_id = ? AND (expiry_date >= UNIX_TIMESTAMP() OR expiry_date = 0)
                                    ORDER BY permanent, expiry_date
                                ) a, (SELECT @pointSum :=0) AS dummy
                                ORDER BY permanent, expiry_date
                            ) b
                            WHERE pointSum >= ?
                            ORDER BY permanent, expiry_date
                            LIMIT 1', [$this->user_id, $warningAction->points]);
                if (!$effectiveExpiryDate)
                {
                    $effectiveExpiryDate = null;
                }
            }
        }

        if (!$visitor->user_id ||
            $visitor->hasPermission('general', 'viewWarning'))
        {
            return $effectiveExpiryDate;
        }

        if ($effectiveExpiryDate)
        {
            $effectiveExpiryDate = ($effectiveExpiryDate - ($effectiveExpiryDate % 3600)) + 3600;
        }

        return $effectiveExpiryDate;
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canViewWarningAction(&$error = null): bool
    {
        /** @var User $user */
        $user = $this->User;

        if (!$user->user_id)
        {
            return false;
        }

        if ($this->action_modifier === 'is_discouraged' && $this->action_type === 'action_type' && !$this->canViewDiscouragedWarningAction($error))
        {
            return false;
        }

        return $user->canViewWarningActions($error);
    }

    public function canViewNonSummaryWarningAction(&$error = null): bool
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->canViewNonSummaryWarningActions($error);
    }

    public function canViewDiscouragedWarningAction(&$error = null): bool
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->canViewDiscouragedWarningActions($error);
    }

    public function canEditWarningAction(&$error = ''): bool
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->canEditWarningActions($error);
    }

    protected function _postSave()
    {
        parent::_postSave();
        // big hammer reset the getter cache
        $this->_getterCache = [];

        $this->svUpdatePendingExpiry();
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->svUpdatePendingExpiry();
    }

    protected function svUpdatePendingExpiry()
    {
        if ($this->User === null)
        {
            return;
        }

        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = Helper::repository(WarningRepo::class);
        $warningRepo->updatePendingExpiryForLater($this->User, true);
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['name'] = true;
        $structure->getters['result'] = true;
        $structure->getters['is_expired'] = true;
        $structure->getters['effective_expiry_date'] = true;
        $structure->getters['is_permanent'] = true;

        return $structure;
    }
}
