<?php

namespace SV\WarningImprovements;

use XF\Entity\User;

class Listener
{
    public static function getWarningRepo(): \SV\WarningImprovements\XF\Repository\Warning
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = \XF::repository('XF:Warning');

        return $warningRepo;
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

    /**
     * @param User $visitor
     * @throws \XF\PrintableException
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    public static function visitorSetup(User &$visitor)
    {
        $userId = (int)$visitor->user_id;

        if ($userId === 0)
        {
            return;
        }

        /** @var \SV\WarningImprovements\XF\Entity\UserOption $option */
        $option = $visitor->Option;
        $pendingWarningExpiry = $option->sv_pending_warning_expiry ?? 0;

        if ($pendingWarningExpiry !== 0 && $pendingWarningExpiry <= \XF::$time)
        {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = \XF::repository('XF:Warning');
            if (\is_callable([$warningRepo, 'processExpiredWarningsForUser']))
            {
                $warningRepo->processExpiredWarningsForUser($visitor, $visitor->is_banned);
            }
        }
    }
}
