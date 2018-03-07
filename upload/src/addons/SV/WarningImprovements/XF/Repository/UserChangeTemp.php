<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Repository;

use XF\Entity\User as UserEntity;

/**
 * Extends \XF\Repository\UserChangeTemp
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    /** @var null|\XF\Entity\UserGroup[] */
    protected $userGroups = null;

    /**
     * @return null|\XF\Entity\UserGroup[]|\XF\Mvc\Entity\ArrayCollection
     */
    public function getCachedUserGroupsList()
    {
        if ($this->userGroups === null)
        {
            /** @var \XF\Repository\UserGroup $userGroupRepo */
            $userGroupRepo = $this->repository('XF:UserGroup');
            $this->userGroups = $userGroupRepo->findUserGroupsForList()->fetch();
        }

        return $this->userGroups;
    }

    /** @var null|array */
    protected $userGroupChangeSet = [];

    /**
     * @param int $userId
     * @return null|int[]
     */
    public function getCachedUserGroupChangeList($userId)
    {
        if (\array_key_exists($userId, $this->userGroupChangeSet))
        {
            return $this->userGroupChangeSet[$userId];
        }

        $this->userGroupChangeSet[$userId] = $this->db()->fetchPairs("select change_key, group_ids
            from xf_user_group_change
            where user_id = ? and change_key like 'warning_action_%'
        ", $userId);

        if (empty( $this->userGroupChangeSet[$userId]))
        {
            $this->userGroupChangeSet[$userId] = null;
        }
        else
        {
            foreach ($this->userGroupChangeSet[$userId] as $key => &$val)
            {
                $val = \array_unique(array_filter(array_map('intval', \explode(',', $val))));
            }
        }

        return $this->userGroupChangeSet[$userId];
    }

    /**
     * @param UserEntity $user
     * @param bool       $showAll
     * @param bool       $showDiscouraged
     * @param bool       $onlyExpired
     * @return \XF\Mvc\Entity\Finder|\XF\Finder\UserChangeTemp
     */
    public function getWarningActions(UserEntity $user, $showAll = false, $showDiscouraged = false, $onlyExpired = false)
    {
        $warningActions = $this->finder('XF:UserChangeTemp');

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
            $warningActions->where('user_id','=', $user->user_id);
        }
        else
        {
            $showDiscouragedWhere = null;

            if (!$showDiscouraged)
            {
                $showDiscouragedWhere = 'AND ' . implode(' AND ', [
                    $warningActions->buildCondition('action_type', '<>', 'field'),
                    $warningActions->buildCondition('action_modifier', '<>', 'is_discouraged')
                ]);
            }

            $warningActions->whereSql("
                user_change_temp_id IN (
                    SELECT MAX(user_change_temp_id)
                    FROM xf_user_change_temp
                    WHERE user_id = {$warningActions->quote($user->user_id)} {$showDiscouragedWhere}
                )
            ");
        }

        $warningActions->order('expiry_date', 'DESC');

        return $warningActions;
    }

    /**
     * @param UserEntity $user
     * @param bool       $showAll
     * @param bool       $showDiscouraged
     * @return int|null
     */
    public function countWarningActions(UserEntity $user, $showAll = false, $showDiscouraged = false)
    {
        return $this->db()->fetchOne($this->getWarningActions($user, $showAll, $showDiscouraged)->getQuery(['countOnly' => true]));
    }
}
