<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Pub\Controller;

use SV\WarningImprovements\Globals;
/**
 * Extends \XF\Pub\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    public static function getActivityDetails(array $activities)
    {
        /** @var \XF\Entity\SessionActivity[] $activities */
        $warningIds = [];
        $warnings = [];
        $em = \XF::em();
        foreach ($activities AS $activity)
        {
            $warningId = $activity->pluckParam('warning_id');
            if ($warningId)
            {
                $warningIds[$warningId] = $warningId;
            }
        }

        if ($warningIds)
        {
            /** @var \XF\Entity\Warning[] $warnings */
            $warnings = \XF::em()->findByIds('XF:Warning', $warningIds, 'User');
        }

        $userIds = [];
        foreach ($activities AS $activity)
        {
            $userId = $activity->user_id;
            if ($userId && !$em->findCached('XF:User', $userId))
            {
                $userIds[$userId] = $userId;
            }
        }

        if ($userIds)
        {
            \XF::em()->findByIds('XF:User', $userIds);
        }

        $router = \XF::app()->router('public');
        $output = [];
        $defaultModPhrase = \XF::phrase('performing_moderation_duties');
        $defaultUserPhrase = \XF::phrase('viewing_members');

        foreach ($activities AS $key => $activity)
        {
            $activityUserId = $activity->user_id;
            if ($activityUserId && $activity->User)
            {
                $user = $activity->User;
                $isMod = $user->is_staff || $user->is_moderator;// || $user->is_admin;
            }
            else
            {
                $isMod = false;
            }
            $defaultPhrase = $isMod
                ? $defaultModPhrase
                : $defaultUserPhrase;

            $visitor = \XF::visitor();
            $warningId = $activity->pluckParam('warning_id');
            $warning = $warningId ? ($warnings[$warningId] ?? null) : null;
            if ($warning && $warning->User)
            {
                $warnedUserId = $warning->user_id;
                Globals::$profileUserId = $warnedUserId && $activityUserId === $warnedUserId ? $warnedUserId : null;
                if ($visitor->canViewWarnings() && $warning->canView())
                {
                    $output[$key] = [
                        'description' => \XF::phrase('sv_viewing_member_warning', ['username' => $warning->User->username]),
                        'title'       => $warning->title,
                        'url'         => $router->buildLink('warnings', $warning),
                    ];
                }
                else
                {
                    $output[$key] = $defaultPhrase;
                }
            }
            else
            {
                $output[$key] = $defaultPhrase;
            }
        }

        return $output;
    }
}