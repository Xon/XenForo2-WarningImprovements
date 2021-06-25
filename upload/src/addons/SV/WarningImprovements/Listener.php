<?php

namespace SV\WarningImprovements;

use XF\Entity\User;

class Listener
{
    public static function criteriaUser($rule, array $data, User $user, &$returnValue)
    {
        switch ($rule)
        {
            case 'warning_points_l':
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::app()->repository('XF:Warning');

                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $points = $days ? $warningRepo->getWarningPointsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points >= $data['points'])
                {
                    $returnValue = true;
                }
                break;
            case 'warning_points_m':
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::app()->repository('XF:Warning');

                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $points = $days ? $warningRepo->getWarningPointsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points <= $data['points'])
                {
                    $returnValue = true;
                }
                break;
            case 'sv_warning_minimum':
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::app()->repository('XF:Warning');

                $minimumWarnings = (int)($data['count'] ?? $data['points'] ?? 0);
                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $count = $warningRepo->getWarningCountsInLastXDays($user, $days, $expired);

                if ($count >= $minimumWarnings)
                {
                    $returnValue = true;
                }
                break;
            case 'sv_warning_maximum':
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::app()->repository('XF:Warning');

                $maximumWarnings = (int)($data['count'] ?? $data['points'] ?? 0);
                $days = (int)($data['days'] ?? 0);
                $expired = (bool)($data['expired'] ?? false);
                $count = $warningRepo->getWarningCountsInLastXDays($user, $days, $expired);

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
        $userId = $visitor->user_id;

        if (empty($userId))
        {
            return;
        }

        /** @var \SV\WarningImprovements\XF\Entity\UserOption $option */
        $option = $visitor->Option;
        $pendingWarningExpiry = $option->sv_pending_warning_expiry ?? 0;

        if ($pendingWarningExpiry && $pendingWarningExpiry <= \XF::$time)
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
