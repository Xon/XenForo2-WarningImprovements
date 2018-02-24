<?php

namespace SV\WarningImprovements;

use XF\Entity\User;

class Listener
{
    public static function criteriaUser(/** @noinspection PhpUnusedParameterInspection */
        $rule, array $data, User $user, &$returnValue)
    {
        switch ($rule)
        {
            case 'warning_points_l': // received at least x points
                break;
            case 'warning_points_m': // received at most x points
                break;
            case 'sv_warning_minimum': // received at least x warnings
                break;
            case 'sv_warning_maximum': // received at most x warnings
                break;
        }
    }

    public static function visitorsetup(User &$visitor)
    {
        $userId = $visitor->user_id;
        if (empty($userId))
        {
            return true;
        }
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
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

    public static $warningDefinitionCategoryId = '';

    public static function entityPreSave_XFWarningDefinition(\XF\Mvc\Entity\Entity $entity)
    {
        if (!is_string(self::$warningDefinitionCategoryId))
        {
            /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $entity */
            $entity->sv_warning_category_id = self::$warningDefinitionCategoryId;
            self::$warningDefinitionCategoryId = '';
        }
    }
}
