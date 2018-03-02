<?php

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\User;
use XF\Entity\WarningAction;

/**
 * Extends \XF\Service\User\WarningPointsChange
 */
class WarningPointsChange extends XFCP_WarningPointsChange
{
    /**
     * @var \SV\WarningImprovements\XF\Entity\WarningAction|WarningAction
     */
    protected $lastAction = null;

    protected function applyWarningAction(WarningAction $action)
    {
        parent::applyWarningAction($action);

        if (!empty(Globals::$warnngObj))
        {
            if ((empty($this->lastWarningAction) || $action->points > $this->lastWarningAction->points) && (!empty($action->sv_post_node_id) || !empty($action->sv_post_thread_id)))
            {
                $this->lastAction = $action;
            }
        }
    }

    protected function getActions()
    {
        $actions = null;

        if (!empty(Globals::$warnngObj))
        {
            /** @var \SV\WarningImprovements\Repository\WarningCategory $warningCategoryRepo */
            $warningCategoryRepo = $this->repository('SV\WarningImprovements:WarningCategory');

            if (Globals::$warnngObj->warning_definition_id === 0)
            {
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = $this->repository('XF:Warning');
                $customWarningDefinition = $warningRepo->getCustomWarning();
                $categories = $warningCategoryRepo->findCategoryParentList($customWarningDefinition->Category);
            }
            else
            {
                $categories = $warningCategoryRepo->findCategoryParentList(Globals::$warnngObj->Definition->Category);
            }

            $actions = $this->finder('XF:WarningAction')->order('points');

            $categoryIds = [0];

            if (!empty($categories))
            {
                /** @var \SV\WarningImprovements\Entity\WarningCategory $parentCategory */
                foreach ($categories as $parentCategory)
                {
                    $categoryIds[] = $parentCategory->warning_category_id;
                }
            }

            $categoryIds[] = Globals::$warnngObj->Definition->Category->warning_category_id;

            $actions = $actions->where('sv_warning_category_id', $categoryIds)->fetch();
        }

        return $actions;
    }

    protected function processPointsIncrease($oldPoints, $newPoints)
    {
        if (empty(Globals::$warnngObj))
        {
            parent::processPointsIncrease($oldPoints, $newPoints);
            return;
        }

        $actions = $this->getActions();

        if (empty($actions))
        {
            return;
        }

        /** @var \XF\Entity\WarningAction $action */
        foreach ($actions AS $action)
        {
            if ($action->points > $oldPoints && $action->points <= $newPoints)
            {
                $this->applyWarningAction($action);
            }
        }

        if (!empty($this->lastAction))
        {
            $postAsUserId = empty($this->lastAction->sv_post_as_user_id) ? Globals::$warnngObj->user_id : $this->lastAction->sv_post_as_user_id;

            /** @var User $postAsUser */
            $postAsUser = $this->em()->find('XF:User', $postAsUserId);

            if (!empty($postAsUser))
            {
                $dateString = date($this->app->options()->sv_warning_date_format, \XF::$time);

                $params = [
                    'username' => $this->user->username,
                    'points' =>  $this->user->warning_count,
                    'warning' => Globals::$warnngObj,
                    'date' => $dateString,
                    'warning_title' => Globals::$warnngObj->title,
                    'warning_points' => Globals::$warnngObj->points,
                    'warning_category' => Globals::$warnngObj->Definition->Category,
                    'threshold' => $this->lastAction->points
                ];

                if (!empty($this->lastAction->sv_post_node_id))
                {
                    if ($forum = $this->em()->find('XF:Forum', $this->lastAction->sv_post_node_id, 'Node'))
                    {
                        $threadCreator = \XF::asVisitor($postAsUser, function() use($forum, $params){
                            /** @var \XF\Service\Thread\Creator $threadCreator */
                            $threadCreator = $this->service('XF:Thread\Creator', $forum);
                            $threadCreator->setIsAutomated();

                            $title = \XF::phrase('Warning_Thread_Title', $params)->render('raw');
                            $messageContent = \XF::phrase('Warning_Thread_Message', $params)->render('raw');

                            $threadCreator->setContent($title, $messageContent);
                            $threadCreator->save();
                        });

                        $threadCreator;
                    }
                }
                else if (!empty($this->lastAction->sv_post_thread_id))
                {
                    if ($thread = $this->em()->find('XF:Thread', $this->lastAction->sv_post_thread_id))
                    {
                        $threadReplier = \XF::asVisitor($postAsUser, function() use($thread, $params){
                            /** @var \XF\Service\Thread\Replier $threadReplier */
                            $threadReplier = $this->service('XF:Thread\Replier', $thread);
                            $threadReplier->setIsAutomated();

                            $messageContent = \XF::phrase('Warning_Thread_Message', $params)->render('raw');

                            $threadReplier->setMessage($messageContent);
                            $threadReplier->save();
                        });

                        $threadReplier;
                    }
                }
            }
        }
    }

    protected function processPointsDecrease($oldPoints, $newPoints, $fromWarningDelete = false)
    {
        if (empty(Globals::$warnngObj) || $fromWarningDelete === false)
        {
            parent::processPointsDecrease($oldPoints, $newPoints, $fromWarningDelete);
            return;
        }

        parent::processPointsDecrease($oldPoints, $newPoints, $fromWarningDelete);

        $actions = $this->getActions();

        if (empty($actions))
        {
            return;
        }

        /** @var \XF\Entity\WarningAction $action */
        foreach ($actions AS $action)
        {
            // If we're deleting, we need to undo warning action effects, even if they're time limited.
            // Points-based will be handled by the triggers so skip. Then only consider where we cross
            // the points threshold from the old (higher) to the new (lower) point values
            if (
                $action->action_length_type == 'points'
                || $action->points > $oldPoints // threshold above where we were
                || $action->points <= $newPoints // we're still at/above the threshold
            )
            {
                continue;
            }

            $this->removeWarningActionEffects($action);
        }
    }
}