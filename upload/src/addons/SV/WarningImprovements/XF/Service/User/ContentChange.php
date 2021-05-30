<?php

namespace SV\WarningImprovements\XF\Service\User;

/**
 * Class ContentChange
 *
 * @package SV\WarningImprovements
 */
class ContentChange extends XFCP_ContentChange
{
    /**
     * ContentChange constructor.
     *
     * @param \XF\App $app
     * @param         $originalUserId
     * @param null    $originalUserName
     */
    public function __construct(\XF\App $app, $originalUserId, $originalUserName = null)
    {
        parent::__construct($app, $originalUserId, $originalUserName);
        if ($this->newUserId !== 0) // deleting user
        {
            $this->steps[] = 'stepReassignWarningActions';
        }
    }

    protected function stepReassignWarningActions()
    {
        /** @var \SV\WarningImprovements\XF\Repository\UserChangeTemp $userChangeTempRepo */
        $userChangeTempRepo = $this->repository('XF:UserChangeTemp');
        /** @var \SV\WarningImprovements\XF\Entity\User $targetUser */
        $targetUser = $this->em()->find('XF:User', $this->newUserId);
        if (!$targetUser)
        {
            return;
        }

        $warningActionsAppliedToSource = $userChangeTempRepo->getWarningActions($this->originalUserId, true, true)->fetch();
        if ($warningActionsAppliedToSource->count())
        {
            $warningActionIds = [];
            /** @var \XF\Entity\UserChangeTemp|\SV\WarningImprovements\XF\Entity\UserChangeTemp $_warningAction */
            foreach ($warningActionsAppliedToSource AS $_warningAction)
            {
                $warningActionDetails = \explode('_', $_warningAction->change_key);
                if (!isset($warningActionDetails[2]))
                {
                    \XF::logException(new \LogicException('Warning ID not available.'));
                    continue;
                }
                $warningActionIds[] = $warningActionDetails[2];
            }
            $warningActionIds =\ array_map('\intval', $warningActionIds);

            if (\count($warningActionIds) === 0)
            {
                \XF::logException(new \LogicException('No warning actions applied to target user.'));
                return;
            }

            $warningActions = $this->finder('XF:WarningAction')->whereIds($warningActionIds)->fetch();

            /** @var \SV\WarningImprovements\XF\Entity\WarningAction $warningAction */
            foreach ($warningActions AS $warningAction)
            {
                $this->applyWarningActionForSVWI($targetUser, $warningAction);
            }
        }
    }

    protected function applyWarningActionForSVWI(\XF\Entity\User &$targetUser, \XF\Entity\WarningAction $warningAction)
    {
        $permanent = ($warningAction->action_length_type == 'permanent');
        $endByPoints = ($warningAction->action_length_type == 'points');

        if ($permanent || $endByPoints)
        {
            $actionEndDate = null;
        }
        else
        {
            $actionEndDate = min(pow(2,32) - 1, strtotime("+{$warningAction->action_length} {$warningAction->action_length_type}"));
        }

        $tempChangeKey = $warningAction->getTempUserChangeKey();

        switch ($warningAction->action)
        {
            case 'ban':
                /** @var \XF\Entity\UserBan $ban */
                $ban = $targetUser->Ban;
                if ($endByPoints)
                {
                    if ($ban && !$ban->end_date)
                    {
                        break;
                    }

                    $this->applyUserBanForSVWI($targetUser, 0, true);
                }
                else
                {
                    if ($ban)
                    {
                        if (!$ban->end_date || ($actionEndDate && $ban->end_date > $actionEndDate))
                        {
                            // already banned and the ban is longer than what would happen here so do nothing
                            break;
                        }
                    }

                    $this->applyUserBanForSVWI($targetUser, $actionEndDate ?: 0, false);
                }
                break;

            case 'discourage':
                /** @var \XF\Service\User\TempChange $changeService */
                $changeService = $this->service('XF:User\TempChange');
                $changeService->applyFieldChange(
                    $targetUser, $tempChangeKey, 'Option.is_discouraged', true, $actionEndDate
                );
                break;

            case 'groups':
                $userGroupChangeKey = 'warning_action_' . $warningAction->warning_action_id;

                /** @var \XF\Service\User\TempChange $changeService */
                $changeService = $this->service('XF:User\TempChange');
                $changeService->applyGroupChange(
                    $targetUser, $tempChangeKey, $warningAction->extra_user_group_ids, $userGroupChangeKey, $actionEndDate
                );
                break;
        }
    }

    /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
    protected function applyUserBanForSVWI(\XF\Entity\User &$targetUser, int $endDate, bool $setTriggered): \XF\Entity\UserBan
    {
        $ban = $targetUser->Ban;
        if (!$ban)
        {
            $reason = \strval(\XF::phrase('warning_ban_reason'));

            /** @var \XF\Entity\UserBan $ban */
            $ban = $targetUser->getRelationOrDefault('Ban', false);
            $ban->user_id = $targetUser->user_id;
            $ban->ban_user_id = 0;
            $ban->user_reason = utf8_substr($reason, 0, 255);
        }

        $ban->end_date = $endDate;
        if ($setTriggered)
        {
            $ban->triggered = true;
        }
        $ban->save();

        return $ban;
    }
}