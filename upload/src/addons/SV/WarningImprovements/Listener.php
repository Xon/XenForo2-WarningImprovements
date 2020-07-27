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

                $days = empty($data['days']) ? 0 : intval($data['days']);

                $expired = !empty($data['expired']);

                $points = $days ? $warningRepo->getWarningPointsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points >= $data['points'])
                {
                    $returnValue = true;
                }
                break;
            case 'warning_points_m':
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::app()->repository('XF:Warning');

                $days = empty($data['days']) ? 0 : intval($data['days']);

                $expired = !empty($data['expired']);

                $points = $days ? $warningRepo->getWarningPointsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points <= $data['points'])
                {
                    $returnValue = true;
                }
                break;
            case 'sv_warning_minimum':
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::app()->repository('XF:Warning');

                $days = empty($data['days']) ? 0 : intval($data['days']);

                $expired = !empty($data['expired']);

                $points = $days ? $warningRepo->getWarningCountsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points >= $data['count'])
                {
                    $returnValue = true;
                }
                break;
            case 'sv_warning_maximum':
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::app()->repository('XF:Warning');
                $days = empty($data['days']) ? 0 : intval($data['days']);

                $expired = !empty($data['expired']);

                $points = $days ? $warningRepo->getWarningCountsInLastXDays($user, $days, $expired) : $user->warning_points;

                if ($points <= $data['count'])
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

        if ($option->offsetExists('sv_pending_warning_expiry') &&
            ($sv_pending_warning_expiry = $option->sv_pending_warning_expiry) &&
            $sv_pending_warning_expiry <= \XF::$time
        )
        {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = \XF::repository('XF:Warning');
            if (is_callable([$warningRepo, 'processExpiredWarningsForUser']))
            {
                $warningRepo->processExpiredWarningsForUser($visitor, $visitor->is_banned);
            }
        }
    }
}
