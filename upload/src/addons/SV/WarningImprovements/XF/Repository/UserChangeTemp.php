<?php

namespace SV\WarningImprovements\XF\Repository;

/**
 * Extends \XF\Repository\UserChangeTemp
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    public function getWarningActions(\XF\Entity\User $user, $showAll = false, $showDiscouraged = false, $onlyExpired = false)
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

    public function countWarningActions(\XF\Entity\User $user, $showAll = false, $showDiscouraged = false)
    {
        return $this->db()->fetchOne($this->getWarningActions($user, $showAll, $showDiscouraged)->getQuery(['countOnly' => true]));
    }

    public function removeExpiredChangesForUser($userId)
    {
        $expired = $this->finder('XF:UserChangeTemp')
                        ->where('expiry_date', '<=', \XF::$time)
                        ->where('expiry_date', '!=', null)
                        ->order('expiry_date')
                        ->fetch(1000);

        /** @var \XF\Service\User\TempChange $changeService */
        $changeService = $this->app()->service('XF:User\TempChange');

        /** @var \XF\Entity\UserChangeTemp $change */
        foreach ($expired AS $change)
        {
            $changeService->expireChange($change);
        }
    }
}
