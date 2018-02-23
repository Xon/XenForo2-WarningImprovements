<?php

namespace SV\WarningImprovements\XF\Repository;

/**
 * Extends \XF\Repository\UserChangeTemp
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    public function getWarningActions(\XF\Entity\User $user, $showAll = false, $showDiscouraged = false, $onlyExpired = false)
    {
        $additionalWhereCondition = '';
        if (!$showDiscouraged)
        {
            $additionalWhereCondition = " AND action_type <> 'field' AND action_modifier <> 'is_discouraged' ";
        }

        if ($showAll)
        {
            $additionalWhereCondition = "AND user_id = ?" . $additionalWhereCondition;
        }
        else
        {
            $additionalWhereCondition = "user_change_temp_id IN (
                SELECT MAX(user_change_temp_id)
                FROM xf_user_change_temp
                WHERE user_id = ? {$additionalWhereCondition}
                GROUP BY action_type, new_value
            )";
        }

        if ($onlyExpired)
        {
            $additionalWhereCondition .= ' AND expiry_date IS NOT NULL AND expiry_date > 0 AND expiry_date < '. intval(\XF::$time) . ' ';
        }

        return $this->db()->fetchAllKeyed("
            SELECT xf_user_change_temp.*, user_change_temp_id as warning_action_id,
                IFNULL(expiry_date, 0xFFFFFFFF) as expiry_date_sort
            FROM xf_user_change_temp
            WHERE {$additionalWhereCondition} and change_key like 'warning_action_%'
            ORDER BY expiry_date_sort DESC
        ", 'warning_action_id', [$user->user_id]);
    }

    public function countWarningActions(\XF\Entity\User $user, $showAll = false, $showDiscouraged = false)
    {
        $additionalWhereCondition = '';
        if (!$showDiscouraged)
        {
            $additionalWhereCondition = " AND action_type <> 'field' AND action_modifier <> 'is_discouraged' ";
        }

        if ($showAll)
        {
            $select = "user_change_temp_id";
        }
        else
        {
            $select = "DISTINCT action_type, new_value";
        }

        return $this->db()->fetchOne("
            SELECT COUNT({$select})
            FROM xf_user_change_temp
            WHERE user_id = ?
            {$additionalWhereCondition}
            AND change_key LIKE 'warning_action_%'
        ", $user->user_id);
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
