<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Pub\Controller;

use SV\WarningImprovements\Globals;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use \SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;

/**
 * Extends \XF\Pub\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    public function actionIndex(ParameterBag $params)
    {
        $reply = parent::actionIndex($params);

        if ($reply instanceof ViewReply && ($warning = $reply->getParam('warning')))
        {
            /** @var ExtendedWarningEntity $warning */
            if ($warning->canEdit())
            {
                $handler = $warning->getHandler();
                $content = $warning->Content;

                $colDef = $warning->structure()->columns['notes'] ?? [];
                $userNoteRequired = !($colDef['default'] ?? false) || !empty($colDef['required']);
                $reply->setParam('userNoteRequired', $userNoteRequired);

                if ($content !== null)
                {
                    $contentActions = $handler->getAvailableContentActions($content);
                    $reply->setParam('contentActions', $contentActions);

                    if ($content->hasRelation('DeletionLog'))
                    {
                        /** @var \XF\Entity\DeletionLog $deletionLog */
                        $deletionLog = $content->getRelation('DeletionLog');
                        if ($deletionLog !== null)
                        {
                            $reply->setParam('contentDeleted', true);
                            $reply->setParam('contentDeleteReason', $deletionLog->delete_reason);
                        }
                    }

                    if ($content->isValidColumn('warning_message') || $content->isValidGetter('warning_message'))
                    {
                        $reply->setParam('contentPublicBanner', $content->get('warning_message'));
                    }
                }
            }
        }

        return $reply;
    }

    public function actionUpdate(): AbstractReply
    {
        throw new \LogicException('Not implemented');
    }

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