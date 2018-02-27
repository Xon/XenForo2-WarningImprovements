<?php

namespace SV\WarningImprovements\XF\Service\User;

/**
 * Extends \XF\Service\User\WarningPointsChange
 */
class WarningPointsChange extends XFCP_WarningPointsChange
{
    /**
     * @var \SV\WarningImprovements\XF\Entity\WarningAction|\XF\Entity\WarningAction
     */
    protected $lastAction = null;

    protected function applyWarningAction(\XF\Entity\WarningAction $action)
    {
        parent::applyWarningAction($action);

        if (!empty(\SV\WarningImprovements\Listener::$warnngObj))
        {
            if ((empty($this->lastWarningAction) || $action->points > $this->lastWarningAction->points) && (!empty($action->sv_post_node_id) || !empty($action->sv_post_thread_id)))
            {
                $this->lastWarningAction = $action;
            }
        }
    }

    protected function processPointsIncrease($oldPoints, $newPoints)
    {
        parent::processPointsIncrease($oldPoints, $newPoints);

        if (!empty($this->lastAction))
        {
            $postAsUserId = empty($this->lastAction->sv_post_as_user_id) ? \SV\WarningImprovements\Listener::$warnngObj->user_id : $this->lastAction->sv_post_as_user_id;
            $postAsUser = $this->em()->find('XF:User', $postAsUserId);

            if (!empty($postAsUser))
            {
                $dateString = date($this->app->options()->sv_warning_date_format, \XF::$time);

                $params = [
                    'username' => $this->user->username,
                    'points' =>  $this->user->warning_count,
                    'warning' => \SV\WarningImprovements\Listener::$warnngObj,
                    'date' => $dateString,
                    'warning_title' => \SV\WarningImprovements\Listener::$warnngObj->title,
                    'warning_points' => \SV\WarningImprovements\Listener::$warnngObj->points,
                    'warning_category' => \SV\WarningImprovements\Listener::$warnngObj->Definition->Category,
                    'threshold' => $this->lastAction->points
                ];

                if (!empty($this->lastAction->sv_post_node_id))
                {
                    if ($forum = $this->em()->find('XF:Forum', $this->lastAction->sv_post_node_id, 'Node'))
                    {
                        $threadCreator = \XF::asVisitor($postAsUser, function($forum, $params){
                            /** @var \XF\Service\Thread\Creator $threadCreator */
                            $threadCreator = $this->service('XF:Thread\Creator', $forum);
                            $threadCreator->setIsAutomated();

                            $title = \XF::phrase('Warning_Thread_Title', $params)->render('raw');
                            $messageContent = \XF::phrase('Warning_Thread_Message', $params)->render('raw');

                            $threadCreator->setContent($title, $messageContent);
                            $threadCreator->save();
                        });
                        $threadCreator($forum, $params);
                    }
                }
                else if (!empty($this->lastAction->sv_post_thread_id))
                {
                    if ($thread = $this->em()->find('XF:Thread', $this->lastAction->sv_post_thread_id))
                    {
                        $threadReplier = \XF::asVisitor($postAsUser, function($thread, $params){
                            /** @var \XF\Service\Thread\Replier $threadReplier */
                            $threadReplier = $this->service('XF:Thread\Replier', $thread);
                            $threadReplier->setIsAutomated();

                            $messageContent = \XF::phrase('Warning_Thread_Message', $params)->render('raw');

                            $threadReplier->setMessage($messageContent);
                            $threadReplier->save();
                        });

                        $threadReplier($thread, $params);
                    }
                }
            }
        }
    }
}