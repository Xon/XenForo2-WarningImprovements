<?php

namespace SV\WarningImprovements\XF\Repository;

use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Entity\UserGroup as UserGroupEntity;
use XF\Finder\UserChangeTemp as UserChangeTempFinder;
use XF\Mvc\Entity\Finder;
use XF\Repository\UserGroup as UserGroupRepo;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function explode;
use function implode;

/**
 * @extends \XF\Repository\UserChangeTemp
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    /** @var null|UserGroupEntity[] */
    protected $userGroups = null;

    /**
     * @return UserGroupEntity[]
     */
    public function getCachedUserGroupsList(): array
    {
        if ($this->userGroups === null)
        {
            $userGroupRepo = Helper::repository(UserGroupRepo::class);
            $this->userGroups = $userGroupRepo->findUserGroupsForList()
                                              ->fetch()
                                              ->toArray();
        }

        return $this->userGroups;
    }

    /** @var null|array */
    protected $userGroupChangeSet = [];

    /**
     * @param int $userId
     * @return null|int[]
     */
    public function getCachedUserGroupChangeList(int $userId): ?array
    {
        if (array_key_exists($userId, $this->userGroupChangeSet))
        {
            return $this->userGroupChangeSet[$userId];
        }

        $this->userGroupChangeSet[$userId] = \XF::db()->fetchPairs("SELECT change_key, group_ids
            FROM xf_user_group_change
            WHERE user_id = ? AND change_key LIKE 'warning_action_%'
        ", $userId);

        if (empty($this->userGroupChangeSet[$userId]))
        {
            $this->userGroupChangeSet[$userId] = null;
        }
        else
        {
            foreach ($this->userGroupChangeSet[$userId] as &$val)
            {
                $val = array_unique(array_filter(array_map('\intval', explode(',', $val))));
            }
        }

        return $this->userGroupChangeSet[$userId];
    }

    /**
     * @param int  $userId
     * @param bool $showAll
     * @param bool $showDiscouraged
     * @param bool $onlyExpired
     * @return Finder|UserChangeTempFinder
     */
    public function getWarningActions(int $userId, bool $showAll = false, bool $showDiscouraged = false, bool $onlyExpired = false)
    {
        $warningActions = Helper::finder(UserChangeTempFinder::class);

        $warningActions->where('change_key', 'LIKE', 'warning_action_%');

        if (!$showDiscouraged)
        {
            $warningActions->where('action_type', '<>', 'field');
            $warningActions->where('action_modifier', '<>', 'is_discouraged');
        }

        if ($onlyExpired)
        {
            $warningActions->where('expiry_date', '<=', \XF::$time);
            $warningActions->where('expiry_date', '!=', null);
        }

        if ($showAll)
        {
            $warningActions->where('user_id', '=', $userId);
        }
        else
        {
            $showDiscouragedWhere = null;

            if (!$showDiscouraged)
            {
                $showDiscouragedWhere = 'AND ' . implode(' AND ', [
                        $warningActions->buildCondition('action_type', '<>', 'field'),
                        $warningActions->buildCondition('action_modifier', '<>', 'is_discouraged'),
                    ]);
            }

            $warningActions->whereSql("
                user_change_temp_id IN (
                    SELECT MAX(user_change_temp_id)
                    FROM xf_user_change_temp
                    WHERE user_id = {$warningActions->quote($userId)} {$showDiscouragedWhere}
                )
            ");
        }

        $warningActions->order('expiry_date', 'DESC');

        return $warningActions;
    }

    public function countWarningActions(UserEntity $user, bool $showAll = false, bool $showDiscouraged = false): ?int
    {
        return \XF::db()->fetchOne($this->getWarningActions($user->user_id, $showAll, $showDiscouraged)->getQuery(['countOnly' => true]));
    }
}
