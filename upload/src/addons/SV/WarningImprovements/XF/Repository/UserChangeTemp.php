<?php

namespace SV\WarningImprovements\XF\Repository;
use XF\Entity\User;

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

    /**
     * @param User $user
     * @param bool $showAll
     * @param bool $showDiscouraged
     * @param bool $onlyExpired
     * @return \XF\Mvc\Entity\Finder|\XF\Finder\UserChangeTemp
     */
    public function getWarningActions(User $user, $showAll = false, $showDiscouraged = false, $onlyExpired = false)
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
     * @param User $user
     * @param bool $showAll
     * @param bool $showDiscouraged
     * @return int|null
     */
    public function countWarningActions(User $user, $showAll = false, $showDiscouraged = false)
    {
        return $this->db()->fetchOne($this->getWarningActions($user, $showAll, $showDiscouraged)->getQuery(['countOnly' => true]));
    }
}
