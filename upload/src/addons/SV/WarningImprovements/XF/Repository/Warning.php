<?php

namespace SV\WarningImprovements\XF\Repository;

use SV\WarningImprovements\Entity\WarningDefault;
use SV\WarningImprovements\XF\Entity\UserOption;
use SV\WarningImprovements\XF\Entity\WarningDefinition;
use XF\Entity\User as UserEntity;
use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use SV\WarningImprovements\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\Warning as WarningEntity;
use XF\Phrase;
use function array_map;
use function count;
use function min;

/**
 * @Extends \XF\Repository\Warning
 */
class Warning extends XFCP_Warning
{
    /**
     * XF2.2.0/XF2.2.1 compatibility shim
     *
     * @param UserEntity $user
     * @param \Closure   $callable
     * @return mixed
     */
    public function asVisitorWithLang(UserEntity $user, \Closure $callable)
    {
        if (\XF::$versionId >= 2020270)
        {
            return \XF::asVisitor($user, $callable, true);
        }

        $oldVisitor = \XF::visitor();
        \XF::setVisitor($user);

        $oldLang = \XF::language();
        $app = \XF::app();
        $newLang = $app->language($user->language_id);
        if (!$newLang->isUsable($user))
        {
            $newLang = $app->language();
        }
        $newLangeOrigTz = $newLang->getTimeZone();
        $newLang->setTimeZone($user->timezone);
        \XF::setLanguage($newLang);

        try
        {
            return $callable();
        }
        finally
        {
            \XF::setVisitor($oldVisitor);

            $newLang->setTimeZone($newLangeOrigTz);
            \XF::setLanguage($oldLang);
        }
    }

    public function getSvWarningReplaceables(UserEntity $warnedUser, ?WarningEntity $warning = null, ?int $pointThreshold = null, bool $forPhrase = false, ?string $contentAction = null, ?array $contentActionOptions = null): array
    {
        /** @var UserEntity|ExtendedUserEntity $warnedUser */
        /** @var WarningEntity|ExtendedWarningEntity|null $warning */
        return $this->asVisitorWithLang($warnedUser, function () use ($warnedUser, $warning, $pointThreshold, $forPhrase, $contentAction, $contentActionOptions) {
            $app = $this->app();
            $router = $app->router('public');
            $dateString = \date($app->options()->sv_warning_date_format ?? 'F d, Y', \XF::$time);
            $staffUser = $warning
                ? $warnedUser->canViewIssuer() ? $warning->WarnedBy : $warning->getAnonymizedIssuer()
                : \XF::visitor();

            $handler = $warning ? $warning->getHandler() : null;
            $content = $warning ? $warning->Content : null;
            $params = $warning ? $warning->toArray() : [];
            foreach ($params as $key => $value)
            {
                if (\is_array($value) || \is_object($value))
                {
                    unset($params[$key]);
                }
            }

            $category = $warning && $warning->Definition && $warning->Definition->Category ? $warning->Definition->Category : null;

            $params = \array_merge($params, [
                'title'                    => $warning && $content ? $handler->getStoredTitle($content) : '',
                'content'                  => $handler && $content ? $handler->getContentForConversation($content) : '',
                'url'                      => $handler && $content ? $handler->getContentUrl($content, true) : '',
                'user_id'                  => $warnedUser->user_id,
                'name'                     => $warnedUser->username,
                'username'                 => $warnedUser->username,
                'staff'                    => $staffUser->username,
                'staff_user_id'            => $staffUser->username,
                'points'                   => $warnedUser->warning_points,
                'notes'                    => $warning ? $warning->notes : \XF::phrase('n_a'),
                'report'                   => $warning && $warning->isValidRelation('Report') && $warning->Report ? $router->buildLink('full:reports', $warning->Report) : \XF::phrase('n_a'),
                'date'                     => $dateString,
                'warning_title'            => $warning ? $warning->title_censored : \XF::phrase('n_a'),
                'warning_title_uncensored' => $warning ? $warning->title : \XF::phrase('n_a'),
                'warning_points'           => $warning ? $warning->points : 0,
                'warning_category'         => $category ? $category->title : \XF::phrase('n_a'),
                'threshold'                => (int)$pointThreshold,
                'warning_link'             => $warning ? $router->buildLink('full:warnings', $warning) : '',
                'content_link'             => $handler ? $handler->getContentUrl($warning->Content, true) : '',
                'content_action'           => $contentAction ? $this->getReadableContentAction($contentAction, $contentActionOptions ?: []) : \XF::phrase('n_a'),
            ]);


            if (!$forPhrase)
            {
                $replacables = [];
                foreach ($params as $key => $value)
                {
                    $replacables['{' . $key . '}'] = (string)$value;
                }
                $params = $replacables;
            }
            else
            {
                foreach ($params as &$value)
                {
                    $value = (string)$value;
                }
            }

            return $params;
        });
    }

    /**
     * @param $contentAction
     * @param array $contentOptions
     *
     * @return null|Phrase
     */
    protected function getReadableContentAction($contentAction, array $contentOptions)
    {
        return \XF::phrase(
            'svWarningImprovements_warning_content_action.' . $contentAction,
            $contentOptions
        )->render('html', ['nameOnInvalid' => false]) ?: \XF::phrase('n_a');
    }

    public function findWarningDefinitionsForListGroupedByCategory(): array
    {
        return parent::findWarningDefinitionsForList()
                     ->order('sv_display_order', 'asc')
                     ->fetch()
                     ->groupBy('sv_warning_category_id');
    }

    public function getCustomWarningDefinition(): WarningDefinition
    {
        /** @var WarningDefinition $warningDefinition */
        $warningDefinition = $this->em->findCached('XF:WarningDefinition', 0);
        if ($warningDefinition)
        {
            return $warningDefinition;
        }

        /** @var WarningDefinition $warningDefinition */
        $warningDefinition = $this->finder('XF:WarningDefinition')
                                  ->where('warning_definition_id', '=', 0)
                                  ->fetchOne();

        return $warningDefinition;
    }

    /**
     * @param int $warningCount
     * @param int $warningTotals
     * @return WarningDefault|null
     * @noinspection PhpUnusedParameterInspection
     */
    public function getWarningDefaultExtension(int $warningCount, int $warningTotals)
    {
        /** @var WarningDefault $warningDefault */
        $warningDefault = $this->finder('SV\WarningImprovements:WarningDefault')
                               ->where('active', '=', 1)
                               ->where('threshold_points', '<', $warningTotals)
                               ->order('threshold_points', 'DESC')
                               ->fetchOne();

        return $warningDefault;
    }

    public function _getWarningTotals(int $userId): array
    {
        $ageLimit = (int)(\XF::options()->svWarningEscalatingDefaultsLimit ?? 0);
        $timeLimit = $ageLimit > 0 ? \XF::$time - $ageLimit * 2629746 : 0;

        $totals = $this->db()->fetchRow('
            SELECT count(points) AS `count`,
                   CAST(IFNULL(sum(points), 0) AS UNSIGNED) AS `total`
            FROM xf_warning
            WHERE user_id = ? AND xf_warning.warning_date >= ?
        ', [$userId, $timeLimit]);
        if (!$totals)
        {
            return ['count' => 0, 'total' => 0];
        }

        return $totals;
    }

    public function escalateDefaultExpirySettingsForUser(UserEntity $user, ?WarningDefinition $definition = null): WarningDefinition
    {
        if ($definition === null)
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

    protected function convertToDays(string $expiryType, int $expiryDuration): int
    {
        switch ($expiryType)
        {
            case 'hours':
                return (int)($expiryDuration / 24);
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

    protected function convertDaysToLargestType(int $expiryDuration): array
    {
        if (($expiryDuration % 365) === 0)
        {
            return ['years', $expiryDuration / 365];
        }
        else if (($expiryDuration % 30) === 0)
        {
            return ['months', $expiryDuration / 30];
        }
        else if (($expiryDuration % 7) === 0)
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
     * @noinspection PhpUnusedParameterInspection
     */
    public function getEffectiveNextExpiry(int $userId, bool $checkBannedStatus)
    {
        return $this->db()->fetchOne('
            SELECT MIN(CAST(expiryDate as SIGNED))
            FROM (
                        SELECT MIN(expiry_date) AS expiryDate
                        FROM xf_warning
                        WHERE user_id = ? AND expiry_date > ? AND is_expired = 0
                        UNION
                        SELECT MIN(expiry_date) AS expiryDate
                        FROM xf_user_change_temp
                        WHERE user_id = ? AND expiry_date > ? AND change_key LIKE \'warning_action_%\'
                        UNION
                        SELECT MIN(end_date) AS expiryDate
                        FROM xf_user_ban
                        WHERE user_id = ? AND end_date > ?
            ) a
            WHERE a.expiryDate IS NOT NULL and a.expiryDate > 0
        ', [$userId, \XF::$time, $userId, \XF::$time, $userId, \XF::$time]);
    }

    public function updatePendingExpiryForLater(UserEntity $user, bool $checkBannedStatus)
    {
        \XF::runOnce('svPendingExpiry.' . $user->user_id, function () use ($user, $checkBannedStatus) {
            $this->updatePendingExpiryFor($user, $checkBannedStatus);
        });
    }

    /**
     * @param UserEntity|null $user
     * @param bool            $checkBannedStatus
     * @return int|null
     */
    public function updatePendingExpiryFor(?UserEntity $user = null, bool $checkBannedStatus = true)
    {
        if ($user === null || $user->Option === null)
        {
            return null;
        }
        $db = $this->db();

        /** @var UserOption $option */
        $option = $user->Option;

        $db->beginTransaction();

        $effectiveNextExpiry = $this->getEffectiveNextExpiry($user->user_id, $checkBannedStatus);

        if ($option->sv_pending_warning_expiry !== $effectiveNextExpiry)
        {
            $option->fastUpdate('sv_pending_warning_expiry', $effectiveNextExpiry);
        }

        $db->commit();

        return $effectiveNextExpiry;
    }

    /**
     * @param UserEntity $user
     * @param bool       $checkBannedStatus
     * @return bool
     * @throws \Exception
     * @throws \XF\PrintableException
     * @noinspection PhpUnusedParameterInspection
     */
    public function processExpiredWarningsForUser(UserEntity $user, bool $checkBannedStatus): bool
    {
        $userId = (int)$user->user_id;
        if ($userId === 0)
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

        /** @var WarningEntity $warning */
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

        // updatePendingExpiryFor is triggered is a warning/ban/user action is changed/deleted
        // but if processExpiredWarningsForUser is called, it means the sv_pending_warning_expiry is set
        // queue via runOnce, and then ensure it is executed
        $this->updatePendingExpiryForLater($user, true);
        \XF::triggerRunOnce();

        return $expired;
    }

    protected $userWarningCountCache = [];

    protected function getCachedWarningsForUser(UserEntity $user, int $days, bool $includeExpired): array
    {
        $rec = $this->userWarningCountCache[$user->user_id][$days] ?? null;
        if (!$rec)
        {
            $params = [$user->user_id, \XF::$time - 86400 * $days];
            $additionalWhere = '';

            if (!$includeExpired)
            {
                $additionalWhere .= ' AND is_expired = 0 ';
            }

            $this->userWarningCountCache[$user->user_id][$days] = $rec = $this->db()->fetchRow("
                SELECT SUM(points) AS total, COUNT(points) AS `count`
                FROM xf_warning
                WHERE user_id = ? AND warning_date > ? {$additionalWhere}
                GROUP BY user_id
            ", $params) ?: [];
        }

        return $rec;
    }

    public function getWarningPointsInLastXDays(UserEntity $user, int $days, bool $includeExpired = false): int
    {
        $value = $this->getCachedWarningsForUser($user, $days, $includeExpired);
        if (!empty($value['total']))
        {
            return (int)$value['total'];
        }

        return 0;
    }

    /**
     * @param UserEntity $user
     * @param int        $days
     * @param bool       $includeExpired
     * @return int
     */
    public function getWarningCountsInLastXDays(UserEntity $user, int $days, bool $includeExpired = false): int
    {
        $value = $this->getCachedWarningsForUser($user, $days, $includeExpired);
        if (!empty($value['count']))
        {
            return (int)$value['count'];
        }

        return 0;
    }

    /**
     * @param WarningEntity|ExtendedWarningEntity $warning
     * @param bool                                $conversation
     * @return ExtendedUserEntity|UserEntity
     */
    public function getWarnedByForUser(WarningEntity $warning, bool $conversation)
    {
        if ($conversation && !(\XF::options()->svWarningImprovAnonymizeConversations ?? false))
        {
            return $warning->WarnedBy;
        }

        if ($warning->User->canViewIssuer()) // the user getting warned
        {
            return $warning->WarnedBy;
        }

        return $warning->getAnonymizedIssuer();
    }

    /**
     * @param WarningEntity|ExtendedWarningEntity $warning
     */
    public function sendWarningAlert(WarningEntity $warning, string $action, string $reason, array $extra = [])
    {
        $exists = $warning->exists();
        $defaults = [
            'reason' => $reason,
            'points' => $warning->points,
            'expiry' => $warning->expiry_date_rounded,
            'title'  => $warning->title_censored,
            'depends_on_addon_id' => 'SV/WarningImprovements',
        ];
        if (!$exists)
        {
            $defaults['warning_id']  = $warning->warning_id;
        }
        $extra = \array_merge($defaults, $extra);

        $warnedBy = $this->getWarnedByForUser($warning, false);
        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->alertFromUser($warning->User, $warnedBy, 'warning_alert', $exists ? $warning->warning_id : 0, $action, $extra);
    }

    /**
     * @return \XF\Mvc\Entity\Repository|UserChangeTemp
     */
    protected function _getWarningActionRepo(): UserChangeTemp
    {
        return $this->repository('XF:UserChangeTemp');
    }
}
