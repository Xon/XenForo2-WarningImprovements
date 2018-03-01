<?php

namespace SV\WarningImprovements\XF\Repository;

use \XF\Entity\User as UserEntity;
use XF\Entity\User;

/**
 * Extends \XF\Repository\Warning
 */
class Warning extends XFCP_Warning
{
    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function findWarningDefinitionsForListGroupedByCategory()
    {
        return parent::findWarningDefinitionsForList()
            ->order('sv_display_order', 'asc')
            ->fetch()
            ->groupBy('sv_warning_category_id');
    }

    /**
     * @return \SV\WarningImprovements\XF\Entity\WarningDefinition
     */
    public function getCustomWarning()
    {
        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $warningDefinition */
        $warningDefinition = $this->finder('XF:WarningDefinition')
            ->where('warning_definition_id', '=', 0)
            ->fetchOne();

        return $warningDefinition;
    }

    /**
     * @return array
     */
    public function getWarningDefaultExtensions()
    {
        return $this->db()->fetchAllKeyed("
            SELECT *
            FROM xf_sv_warning_default
            ORDER BY threshold_points
        ", 'warning_default_id');
    }

    /**
     * @param int $userId
     * @param bool $checkBannedStatus
     * @return int|null
     */
    public function getEffectiveNextExpiry($userId, $checkBannedStatus)
    {
        $db = $this->db();

        $nextWarningExpiry = $db->fetchOne('
            SELECT min(expiry_date)
            FROM xf_warning
            WHERE user_id = ? AND expiry_date > 0 AND is_expired = 0
        ', $userId);
        if (empty($nextWarningExpiry))
        {
            $nextWarningExpiry = null;
        }

        $warningActionExpiry = $db->fetchOne('
            SELECT min(expiry_date)
            FROM xf_user_change_temp
            WHERE user_id = ? AND expiry_date > 0 AND change_key LIKE \'warning_action_%\';
        ', $userId);
        if (empty($warningActionExpiry))
        {
            $warningActionExpiry = null;
        }

        $banExpiry = null;
        if ($checkBannedStatus)
        {
            $banExpiry = $db->fetchOne('
                SELECT min(end_date)
                FROM xf_user_ban
                WHERE user_id = ? AND end_date > 0
            ', $userId);
            if (empty($banExpiry))
            {
                $banExpiry = null;
            }
        }

        $effectiveNextExpiry = null;
        if ($nextWarningExpiry)
        {
            $effectiveNextExpiry = $nextWarningExpiry;
        }
        if ($warningActionExpiry && $warningActionExpiry > $effectiveNextExpiry)
        {
            $effectiveNextExpiry = $warningActionExpiry;
        }
        if ($banExpiry && $banExpiry > $effectiveNextExpiry)
        {
            $effectiveNextExpiry = $banExpiry;
        }

        return $effectiveNextExpiry;
    }

    /**
     * @param UserEntity|null $user
     * @param bool            $checkBannedStatus
     * @return int|null
     */
    public function updatePendingExpiryFor(UserEntity $user = null, $checkBannedStatus)
    {
        if (!$user || !$user->Option)
        {
            return null;
        }
        $db = $this->db();

        $db->beginTransaction();

        $effectiveNextExpiry = $this->getEffectiveNextExpiry($user->user_id, $checkBannedStatus);

        $user->Option->fastUpdate('sv_pending_warning_expiry', $effectiveNextExpiry);

        $db->commit();

        return $effectiveNextExpiry;
    }

    /**
     * @param UserEntity $user
     * @param bool       $checkBannedStatus
     * @return bool
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function processExpiredWarningsForUser(UserEntity $user, $checkBannedStatus)
    {
        $userId = $user->user_id;
        if (!$userId)
        {
            return false;
        }

        $warnings = $this->finder('XF:Warning')
                         ->where('expiry_date', '<=', \XF::$time)
                         ->where('expiry_date', '>', 0)
                         ->where('is_expired', 0)
                         ->where('user_id', $userId)
                         ->fetch();
        $expired = $warnings->count() > 0;

        /** @var \XF\Entity\Warning $warning */
        foreach ($warnings AS $warning)
        {
            $warning->is_expired = true;
            $warning->setOption('log_moderator', false);
            $warning->save();
        }

        $changes = $this->finder('XF:UserChangeTemp')
                        ->where('expiry_date', '<=', \XF::$time)
                        ->where('expiry_date', '!=', null)
                        ->order('expiry_date')
                        ->where('user_id', $userId)
                        ->fetch(1000);

        /** @var \XF\Service\User\TempChange $changeService */
        $changeService = $this->app()->service('XF:User\TempChange');

        $expired = $expired || $changes->count() > 0;

        /** @var \XF\Entity\UserChangeTemp $change */
        foreach ($changes AS $change)
        {
            $changeService->expireChange($change);
        }

        if ($checkBannedStatus)
        {
            $bans = $this->finder('XF:UserBan')
                         ->where('expiry_date', '<=', \XF::$time)
                         ->where('expiry_date', '>', 0)
                         ->where('user_id', $userId)
                         ->fetch();
            $expired = $expired || $bans->count() > 0;

            /** @var \XF\Entity\UserBan $userBan */
            foreach ($bans AS $userBan)
            {
                $userBan->delete();
            }
        }

        return $expired;
    }

    protected $userWarningCountCache = [];

    /**
     * @param User $user
     * @param int  $days
     * @param bool $includeExpired
     * @return mixed
     */
    protected function getCachedWarningsForUser(User $user, $days, $includeExpired)
    {
        if (!isset($this->userWarningCountCache[$user->user_id][$days]))
        {
            $params = [$user->user_id, \XF::$time - 86400 * $days];
            $additionalWhere = '';

            if (!$includeExpired)
            {
                $additionalWhere .= ' AND is_expired = 0 ';
            }

            $this->userWarningCountCache[$user->user_id][$days] = $this->db()->fetchRow("
                SELECT SUM(points) AS total, COUNT(points) AS `count`
                FROM xf_warning
                WHERE user_id = ? AND warning_date > ? {$additionalWhere}
                GROUP BY user_id
            ", $params);
        }

        return $this->userWarningCountCache[$user->user_id][$days];
    }

    /**
     * @param User $user
     * @param int  $days
     * @param bool $includeExpired
     * @return int
     */
    public function getWarningPointsInLastXDays(User $user, $days, $includeExpired = false)
    {
        $value = $this->getCachedWarningsForUser($user, $days, $includeExpired);
        if (!empty($value['total']))
        {
            return $value['total'];
        }

        return 0;
    }

    /**
     * @param User $user
     * @param int  $days
     * @param bool $includeExpired
     * @return int
     */
    public function getWarningCountsInLastXDays(User $user, $days, $includeExpired = false)
    {
        $value = $this->getCachedWarningsForUser($user, $days, $includeExpired);
        if (!empty($value['count']))
        {
            return $value['count'];
        }

        return 0;
    }

    /**
     * @return \XF\Mvc\Entity\Repository|UserChangeTemp
     */
    protected function _getWarningActionRepo()
    {
        return $this->repository('XF:UserChangeTemp');
    }
}
