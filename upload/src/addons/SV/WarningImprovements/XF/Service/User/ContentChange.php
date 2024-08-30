<?php

namespace SV\WarningImprovements\XF\Service\User;

use SV\StandardLib\Helper;
use SV\WarningImprovements\XF\Entity\User as ExtendedUserEntity;
use SV\WarningImprovements\XF\Entity\UserChangeTemp as ExtendedUserChangeTempEntity;
use SV\WarningImprovements\XF\Entity\WarningAction as ExtendedWarningActionEntity;
use SV\WarningImprovements\XF\Repository\UserChangeTemp as ExtendedUserChangeTempRepo;
use XF\App;
use XF\Entity\User as UserEntity;
use XF\Entity\UserBan as UserBanEntity;
use XF\Entity\UserChangeTemp as UserChangeTempEntity;
use XF\Entity\WarningAction as WarningActionEntity;
use XF\Finder\WarningAction as WarningActionFinder;
use XF\Repository\UserChangeTemp as UserChangeTempRepo;
use XF\Service\User\TempChange as TempChangeService;

/**
 * @extends \XF\Service\User\ContentChange
 */
class ContentChange extends XFCP_ContentChange
{
    /**
     * ContentChange constructor.
     *
     * @param App $app
     * @param int     $originalUserId
     * @param ?string $originalUserName
     */
    public function __construct(App $app, $originalUserId, $originalUserName = null)
    {
        parent::__construct($app, $originalUserId, $originalUserName);
        if ($this->newUserId !== 0) // deleting user
        {
            $this->steps[] = 'stepReassignWarningActions';
        }
    }

    protected function stepReassignWarningActions()
    {
        /** @var ExtendedUserChangeTempRepo $userChangeTempRepo */
        $userChangeTempRepo = Helper::repository(UserChangeTempRepo::class);
        /** @var ?ExtendedUserEntity $targetUser */
        $targetUser = Helper::find(UserEntity::class, $this->newUserId);
        if ($targetUser === null)
        {
            return;
        }

        $warningActionsAppliedToSource = $userChangeTempRepo->getWarningActions($this->originalUserId, true, true)->fetch();
        if ($warningActionsAppliedToSource->count())
        {
            $warningActionIds = [];
            /** @var UserChangeTempEntity|ExtendedUserChangeTempEntity $_warningAction */
            foreach ($warningActionsAppliedToSource AS $_warningAction)
            {
                $warningActionDetails = \explode('_', $_warningAction->change_key);
                $warningActionId = $warningActionDetails[2] ?? null;
                if (!$warningActionId)
                {
                    \XF::logException(new \LogicException('Warning ID not available.'));
                    continue;
                }
                $warningActionIds[] = $warningActionId;
            }
            $warningActionIds = \array_map('\intval', $warningActionIds);

            if (\count($warningActionIds) === 0)
            {
                \XF::logException(new \LogicException('No warning actions applied to target user.'));
                return;
            }

            $warningActions = Helper::finder(WarningActionFinder::class)->whereIds($warningActionIds)->fetch();

            /** @var ExtendedWarningActionEntity $warningAction */
            foreach ($warningActions AS $warningAction)
            {
                $this->applyWarningActionForSVWI($targetUser, $warningAction);
            }
        }
    }

    protected function applyWarningActionForSVWI(UserEntity &$targetUser, WarningActionEntity $warningAction)
    {
        $permanent = ($warningAction->action_length_type === 'permanent');
        $endByPoints = ($warningAction->action_length_type === 'points');

        if ($permanent || $endByPoints)
        {
            $actionEndDate = null;
        }
        else
        {
            $actionEndDate = \min(\pow(2,32) - 1, (int)\strtotime("+{$warningAction->action_length} {$warningAction->action_length_type}"));
        }

        $tempChangeKey = $warningAction->getTempUserChangeKey();

        switch ($warningAction->action)
        {
            case 'ban':
                /** @var UserBanEntity $ban */
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
                $changeService = Helper::service(TempChangeService::class);
                $changeService->applyFieldChange(
                    $targetUser, $tempChangeKey, 'Option.is_discouraged', true, $actionEndDate
                );
                break;

            case 'groups':
                $userGroupChangeKey = 'warning_action_' . $warningAction->warning_action_id;

                $changeService = Helper::service(TempChangeService::class);
                $changeService->applyGroupChange(
                    $targetUser, $tempChangeKey, $warningAction->extra_user_group_ids, $userGroupChangeKey, $actionEndDate
                );
                break;
        }
    }

    /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
    protected function applyUserBanForSVWI(UserEntity &$targetUser, int $endDate, bool $setTriggered): UserBanEntity
    {
        $ban = $targetUser->Ban;
        if (!$ban)
        {
            $reason = \strval(\XF::phrase('warning_ban_reason'));

            /** @var UserBanEntity $ban */
            $ban = $targetUser->getRelationOrDefault('Ban', false);
            $ban->user_id = $targetUser->user_id;
            $ban->ban_user_id = 0;
            $ban->user_reason = \mb_strcut($reason, 0, 255);
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