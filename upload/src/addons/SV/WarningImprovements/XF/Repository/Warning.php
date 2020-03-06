<?php

namespace SV\WarningImprovements\XF\Repository;

use SV\WarningImprovements\Entity\WarningDefault;
use SV\WarningImprovements\XF\Entity\WarningDefinition;
use XF\Entity\User as UserEntity;
use SV\WarningImprovements\XF\Entity\Warning as WarningEntity;

/**
 * Extends \XF\Repository\Warning
 */
class Warning extends XFCP_Warning
{
    /**
     * @param UserEntity                            $user
     * @param \XF\Entity\Warning|WarningEntity|null $warning
     * @param int|null                              $pointThreshold
     * @param bool                                  $forPhrase
     * @return array
     */
    public function getSvWarningReplaceables(UserEntity $user, WarningEntity $warning = null, $pointThreshold = null, $forPhrase = false)
    {
        $app = $this->app();
        $router = $app->router('public');
        $dateString = date($app->options()->sv_warning_date_format, \XF::$time);
        $staffUser = $warning ? $warning->WarnedBy : \XF::visitor();

        $handler = $warning ? $warning->getHandler() : null;
        $content = $warning ? $warning->Content : null;
        $params = $warning ? $warning->toArray() : [];
        foreach ($params as $key => $value)
        {
            if (is_array($value) || is_object($value))
            {
                unset($params[$key]);
            }
        }
        $params = \array_merge($params, [
            'title'            => $warning && $content ? $handler->getStoredTitle($content) : '',
            'content'          => $handler && $content ? $handler->getContentForConversation($content) : '',
            'url'              => $handler && $content ? $handler->getContentUrl($content, true) : '',
            'user_id'          => $user->user_id,
            'name'             => $user->username,
            'username'         => $user->username,
            'staff'            => $staffUser->username,
            'staff_user_id'    => $staffUser->username,
            'points'           => $user->warning_points,
            'report'           => $warning && $warning->isValidRelation('Report') && $warning->Report ? $router->buildLink('full:reports', $warning->Report) : \XF::phrase('n_a'),
            'date'             => $dateString,
            'warning_title'    => $warning ? $warning->title : \XF::phrase('n_a'),
            'warning_points'   => $warning ? $warning->points : 0,
            'warning_category' => $warning && $warning->Definition && $warning->Definition->Category ? $warning->Definition->Category->title : \XF::phrase('n_a'),
            'threshold'        => $pointThreshold,
            'warning_link'     => $warning ? $router->buildLink('full:warnings', $warning) : null,
            'content_link'     => $handler ? $handler->getContentUrl($warning->Content, true) : null,
        ]);

        if (!$forPhrase)
        {
            $replacables = [];
            foreach ($params as $key => $value)
            {
                $replacables['{' . $key . '}'] = $value;
            }
            $params = $replacables;
        }
        else
        {
            foreach ($params as $key => &$value)
            {
                $value = (string)$value;
            }
        }

        return $params;
    }

    /**
     * @return WarningDefinition[]
     */
    public function findWarningDefinitionsForListGroupedByCategory()
    {
        return parent::findWarningDefinitionsForList()
                     ->order('sv_display_order', 'asc')
                     ->fetch()
                     ->groupBy('sv_warning_category_id');
    }

    /**
     * @return WarningDefinition
     */
    public function getCustomWarningDefinition()
    {
        /** @var WarningDefinition $warningDefinition */
        $warningDefinition = $this->em->findCached('XF:WarningDefinition', 0);
        if ($warningDefinition)
        {
            return $warningDefinition;
        }

        $warningDefinition = $this->finder('XF:WarningDefinition')
                                  ->where('warning_definition_id', '=', 0)
                                  ->fetchOne();

        return $warningDefinition;
    }

    /**
     * @param int $warningCount
     * @param int $warningTotals
     * @return WarningDefault
     */
    public function getWarningDefaultExtension(/** @noinspection PhpUnusedParameterInspection */
        $warningCount, $warningTotals)
    {
        /** @var WarningDefault $warningDefault */
        $warningDefault = $this->finder('SV\WarningImprovements:WarningDefault')
                               ->where('active', '=', 1)
                               ->where('threshold_points', '<', $warningTotals)
                               ->order('threshold_points', 'DESC')
                               ->fetchOne();

        return $warningDefault;
    }

    public function _getWarningTotals($userId)
    {
        return $this->db()->fetchRow('
            SELECT count(points) AS `count`, sum(points) AS `total`
            FROM xf_warning
            WHERE user_id = ?
        ', $userId);
    }

    /**
     * @param UserEntity             $user
     * @param WarningDefinition|null $definition
     * @return WarningDefinition
     */
    public function escalateDefaultExpirySettingsForUser($user, WarningDefinition $definition = null)
    {
        if ($definition == null)
        {
            // todo - copy how getGuestUser works
            throw new \LogicException('Require a warning definition to be specified');
        }
        if ($definition->expiry_type === 'never')
        {
            return $definition;
        }

        $definition = clone $definition;

        $totals = $this->_getWarningTotals($user->user_id);
        $warningDefault = $this->getWarningDefaultExtension($totals['count'], $totals['total']);

        if ($warningDefault && !empty($warningDefault->expiry_extension))
        {
            if ($warningDefault->expiry_type === 'never')
            {
                $definition->expiry_type = $warningDefault->expiry_type;
                $definition->expiry_default = $warningDefault->expiry_extension;
            }
            else
            {
                $expiryDuration = $this->convertToDays($definition->expiry_type, $definition->expiry_default) +
                                  $this->convertToDays($warningDefault->expiry_type, $warningDefault->expiry_extension);

                $expiryParts = $this->convertDaysToLargestType($expiryDuration);

                $definition->expiry_type = $expiryParts[0];
                $definition->expiry_default = $expiryParts[1];
            }
        }

        $definition->setReadOnly(true);

        return $definition;
    }

    /**
     * @param string $expiryType
     * @param int    $expiryDuration
     * @return int
     */
    protected function convertToDays($expiryType, $expiryDuration)
    {
        switch ($expiryType)
        {
            case 'hours':
                return $expiryDuration / 24;
            case 'days':
                return $expiryDuration;
            case 'weeks':
                return $expiryDuration * 7;
            case 'months':
                return $expiryDuration * 30;
            case 'years':
                return $expiryDuration * 365;
        }
        \XF::logError("Unknown expiry type: " . $expiryType, true);

        return $expiryDuration;
    }

    /**
     * @param $expiryDuration
     * @return array
     */
    protected function convertDaysToLargestType($expiryDuration)
    {
        if (($expiryDuration % 365) == 0)
        {
            return ['years', $expiryDuration / 365];
        }
        else if (($expiryDuration % 30) == 0)
        {
            return ['months', $expiryDuration / 30];
        }
        else if (($expiryDuration % 7) == 0)
        {
            return ['weeks', $expiryDuration / 7];
        }
        else
        {
            return ['days', $expiryDuration];
        }
    }

    /**
     * @param int  $userId
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
    public function updatePendingExpiryFor(/** @noinspection PhpOptionalBeforeRequiredParametersInspection */UserEntity $user = null, $checkBannedStatus)
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
                         ->where('end_date', '<=', \XF::$time)
                         ->where('end_date', '>', 0)
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
     * @param UserEntity $user
     * @param int        $days
     * @param bool       $includeExpired
     * @return mixed
     */
    protected function getCachedWarningsForUser(UserEntity $user, $days, $includeExpired)
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
     * @param UserEntity $user
     * @param int        $days
     * @param bool       $includeExpired
     * @return int
     */
    public function getWarningPointsInLastXDays(UserEntity $user, $days, $includeExpired = false)
    {
        $value = $this->getCachedWarningsForUser($user, $days, $includeExpired);
        if (!empty($value['total']))
        {
            return $value['total'];
        }

        return 0;
    }

    /**
     * @param UserEntity $user
     * @param int        $days
     * @param bool       $includeExpired
     * @return int
     */
    public function getWarningCountsInLastXDays(UserEntity $user, $days, $includeExpired = false)
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
