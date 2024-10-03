<?php

namespace SV\WarningImprovements;

use SV\StandardLib\Helper;
use SV\WarningImprovements\XF\Entity\UserOption as ExtendedUserOptionEntity;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Entity\User;
use XF\Finder\User as UserFinder;
use XF\Pub\App as PubApp;
use XF\Repository\Warning as WarningRepo;
use function is_callable;

class Listener
{
    /** @var bool */
    public static $doPartialVisitorReload = true;

    public static function getWarningRepo(): ExtendedWarningRepo
    {
        return Helper::repository(WarningRepo::class);
    }

    public static function criteriaUser($rule, array $data, User $user, &$returnValue)
    {
        switch ($rule)
        {
            case 'warning_points_l':
                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $points = $days ? static::getWarningRepo()->getWarningPointsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points >= $data['points'])
                {
                    $returnValue = true;
                }
                break;
            case 'warning_points_m':
                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $points = $days ? static::getWarningRepo()->getWarningPointsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points <= $data['points'])
                {
                    $returnValue = true;
                }
                break;
            case 'sv_warning_minimum':
                $minimumWarnings = (int)($data['count'] ?? ($data['points'] ?? 0));
                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $count = static::getWarningRepo()->getWarningCountsInLastXDays($user, $days, $expired);

                if ($count >= $minimumWarnings)
                {
                    $returnValue = true;
                }
                break;
            case 'sv_warning_maximum':
                $maximumWarnings = (int)($data['count'] ?? ($data['points'] ?? 0));
                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $count = static::getWarningRepo()->getWarningCountsInLastXDays($user, $days, $expired);

                if ($count <= $maximumWarnings)
                {
                    $returnValue = true;
                }
                break;
        }
    }

    public static function visitorSetup(User &$visitor)
    {
        if (!(\XF::app() instanceof PubApp))
        {
            return;
        }

        $userId = (int)$visitor->user_id;

        if ($userId === 0)
        {
            return;
        }

        /** @var ExtendedUserOptionEntity $option */
        $option = $visitor->Option;
        $pendingWarningExpiry = $option->sv_pending_warning_expiry ?? 0;

        if ($pendingWarningExpiry !== 0 && $pendingWarningExpiry <= \XF::$time)
        {
            /** @var ExtendedWarningRepo $warningRepo */
            $warningRepo = Helper::repository(WarningRepo::class);
            if (is_callable([$warningRepo, 'processExpiredWarningsForUser']))
            {
                $expired = $warningRepo->processExpiredWarningsForUser($visitor, $visitor->is_banned);
                if ($expired)
                {
                    // permissions have likely changed.
                    if (static::$doPartialVisitorReload)
                    {
                        $caches = [
                            'Ban',
                            'PermissionSet',
                            'warning_count',
                        ];
                        // Do a partial reload of the visitor object since only a few fields change
                        $row = \XF::db()->fetchRow('
                        SELECT 
                            is_banned
                            ,permission_combination_id
                            ,user_group_id
                            ,display_style_group_id
                            ,secondary_group_ids
                        FROM xf_user 
                        WHERE user_id = ?
                        ', $visitor->user_id);
                        $columns = $visitor->structure()->columns;
                        $em = \XF::em();
                        foreach ($row as $field => $sourceValue)
                        {
                            $column = $columns[$field] ?? null;
                            if ($column !== null)
                            {
                                $value = $em->decodeValueFromSourceExtended($column['type'], $sourceValue, $column);
                                $visitor->setAsSaved($field, $value);
                            }
                        }
                        foreach ($caches as $cache)
                        {
                            $visitor->clearCache($cache);
                        }
                    }
                    else
                    {
                        $visitor = Helper::finder(UserFinder::class)
                                         ->whereId($visitor->user_id)
                                         ->fetchOne();
                    }
                }
            }
        }
    }
}
