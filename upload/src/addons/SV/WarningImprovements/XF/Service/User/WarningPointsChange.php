<?php
/**
 * @noinspection PhpUnusedParameterInspection
 */

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Entity\WarningCategory;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\User;
use SV\WarningImprovements\XF\Entity\Warning;
use XF\App;
use XF\Entity\WarningAction;
use XF\Entity\Report;
use XF\Mvc\Entity\AbstractCollection;

/**
 * Extends \XF\Service\User\WarningPointsChange
 */
class WarningPointsChange extends XFCP_WarningPointsChange
{
    /**
     * @var \SV\WarningImprovements\XF\Entity\WarningAction|WarningAction
     */
    protected $lastAction = null;

    /** @var Warning */
    protected $warning = null;

    /** @var Report */
    protected $report = null;

    /** @var WarningCategory */
    protected $nullCategory = null;

    /** @var WarningCategory[] */
    protected $warningCategories = [];

    public function __construct(App $app, User $user)
    {
        parent::__construct($app, $user);

        $this->setWarning(Globals::$warningObj);

        $this->nullCategory = \XF::em()->create('SV\WarningImprovements:WarningCategory');
        $this->nullCategory->setTrusted('warning_category_id', null);
        $this->nullCategory->setReadOnly(true);

        /** @var \SV\WarningImprovements\Repository\WarningCategory $warningCategoryRepo */
        $warningCategoryRepo = $this->repository('SV\WarningImprovements:WarningCategory');
        $this->warningCategories = $warningCategoryRepo->findCategoryList()->fetch()->toArray();
    }

    /**
     * @param Warning $warning
     */
    public function setWarning(Warning $warning = null)
    {
        $this->warning = $warning;
    }

    protected function applyWarningAction(WarningAction $action)
    {
        /** @var \SV\WarningImprovements\XF\Entity\WarningAction $action */
        parent::applyWarningAction($action);

        if ((empty($this->lastWarningAction) || $action->points > $this->lastWarningAction->points) &&
            (!empty($action->sv_post_node_id) || !empty($action->sv_post_thread_id)))
        {
            $this->lastAction = $action;
        }
    }

    protected function getActions(string $direction, bool $fromDelete = false): AbstractCollection
    {
        return $this->finder('XF:WarningAction')
                    ->order('points', $direction)
                    ->fetch();
    }

    /**
     * Works out the total points per category and changes
     *
     * @param bool $removePoints
     * @return WarningTotal[]
     */
    protected function getCategoryPoints(bool $removePoints = false): array
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = \XF::repository('XF:Warning');
        /** @var Warning[] $warnings */
        $warnings = $warningRepo->findUserWarningsForList($this->user->user_id)
                                ->where('is_expired', '=', 0)
                                ->fetch()
                                ->toArray();

        /** @var WarningTotal[] $warningPoints */
        $warningPoints = [];
        $warningPoints[0] = new WarningTotal($this->nullCategory);

        foreach ($this->warningCategories as $categoryId => $category)
        {
            $warningPoints[$categoryId] = new WarningTotal($category);
        }
        // build the category tree
        foreach ($this->warningCategories as $categoryId => $category)
        {
            $parentId = $category->parent_category_id ?: 0;
            if (isset($warningPoints[$parentId]))
            {
                $warningPoints[$categoryId]->parent = $warningPoints[$parentId];
            }
        }

        if ($removePoints)
        {
            $oldWarning = $this->warning;
            $newWarning = null;
            // warning has been deleted already, so add it to the list of warnings to consider
            $warnings[$oldWarning->warning_id] = $oldWarning;
        }
        else
        {
            $newWarning = $this->warning;
            $oldWarning = null;
            // warning has been added already
        }

        // compute per-category totals (deleted warning categories and globals map to 0)
        foreach ($warnings as $warning)
        {
            /** @var WarningCategory $category */
            $category = $warning->Definition ? $warning->Definition->Category : null;
            $categoryId = $category ? ($category->warning_category_id ?: 0) : 0;
            if (empty($warningPoints[$categoryId]))
            {
                // the warning definition has been deleted, dump points into global points pool and don't error
                $categoryId = 0;
            }

            $warningTotal = $warningPoints[$categoryId];

            if ($newWarning === null ||
                $warning->warning_id != $newWarning->warning_id)
            {
                $warningTotal->addOld($warning->points);
            }
            if ($oldWarning === null ||
                $warning->warning_id != $oldWarning->warning_id)
            {
                $warningTotal->addNew($warning->points);
            }
        }

        /** @var WarningTotal[] $warningPointsCumulative */
        return $warningPoints;
    }

    protected function processPointsIncrease($oldPoints, $newPoints)
    {
        if (!$this->warning)
        {
            parent::processPointsIncrease($oldPoints, $newPoints);

            return;
        }

        $actions = $this->getActions('ASC');

        if (empty($actions))
        {
            return;
        }

        $categoryPoints = $this->getCategoryPoints();

        /** @var \SV\WarningImprovements\XF\Entity\WarningAction $action */
        foreach ($actions AS $action)
        {
            $categoryId = $action->sv_warning_category_id ?: 0;
            if (empty($categoryPoints[$categoryId]))
            {
                // category has been deleted, but the warning action hasn't been updated, consider it as a global action
                $categoryId = 0;
            }

            $points = $categoryPoints[$categoryId];
            if ($action->points > $points->oldPoints && $action->points <= $points->newPoints)
            {
                $this->applyWarningAction($action);
            }
        }

        $this->warningActionNotifications();
    }

    public function warningActionNotifications()
    {
        if ($this->lastAction)
        {
            /** @var User $postAsUser */
            $postAsUser = null;
            $postAsUserId = $this->lastAction->sv_post_as_user_id ?? 0;
            if ($postAsUserId)
            {
                $postAsUser = $this->em()->find('XF:User', $postAsUserId);
            }

            if (!$postAsUserId)
            {
                $postAsUser = \XF::visitor();
            }

            if ($postAsUser)
            {
                /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
                $warningRepo = \XF::repository('XF:Warning');
                $params = $warningRepo->getSvWarningReplaceables($this->user, $this->warning, $this->lastAction->points, true);

                if ($nodeId = $this->lastAction->sv_post_node_id)
                {
                    /** @var \SV\MultiPrefix\XF\Entity\Forum $forum */
                    $forum = $this->em()->find('XF:Forum', $nodeId);

                    if ($forum)
                    {
                        /** @var \XF\Service\Thread\Creator $threadCreator */
                        $threadCreator = \XF::asVisitor($postAsUser, function () use ($forum, $params) {
                            /** @var \XF\Service\Thread\Creator $threadCreator */
                            $threadCreator = $this->service('XF:Thread\Creator', $forum);
                            $threadCreator->setIsAutomated();

                            $defaultPrefix = $forum->sv_default_prefix_ids ?? $forum->default_prefix_id;
                            if ($defaultPrefix)
                            {
                                $threadCreator->setPrefix($defaultPrefix);
                            }

                            $title = \XF::phrase('Warning_Thread.Title', $params)->render('raw');
                            $messageContent = \XF::phrase('Warning_Thread.Message', $params)->render('raw');

                            $threadCreator->setContent($title, $messageContent);
                            $threadCreator->save();

                            return $threadCreator;
                        });
                        \XF::runLater(function () use ($threadCreator, $postAsUser){
                            \XF::asVisitor($postAsUser, function () use ($threadCreator) {
                                $threadCreator->sendNotifications();
                            });
                        });
                    }
                }
                else if ($threadId = $this->lastAction->sv_post_thread_id)
                {
                    if ($thread = $this->em()->find('XF:Thread', $threadId))
                    {
                        /** @var \XF\Service\Thread\Replier $threadReplier */
                        $threadReplier = \XF::asVisitor($postAsUser, function () use ($thread, $params) {
                            /** @var \XF\Service\Thread\Replier $threadReplier */
                            $threadReplier = $this->service('XF:Thread\Replier', $thread);
                            $threadReplier->setIsAutomated();

                            $messageContent = \XF::phrase('Warning_Thread.Message', $params)->render('raw');

                            $threadReplier->setMessage($messageContent);
                            $threadReplier->save();

                            return $threadReplier;
                        });

                        \XF::runLater(function () use ($threadReplier, $postAsUser){
                            \XF::asVisitor($postAsUser, function () use ($threadReplier) {
                                $threadReplier->sendNotifications();
                            });
                        });
                    }
                }
            }
        }
    }

    protected function processPointsDecrease($oldPoints, $newPoints, $fromWarningDelete = false)
    {
        if (!$this->warning)
        {
            parent::processPointsDecrease($oldPoints, $newPoints, $fromWarningDelete);

            return;
        }

        $categoryPoints = $this->getCategoryPoints(true);

        $triggers = $this->db()->fetchAllKeyed("
			SELECT action_trigger.*, warning_action.sv_warning_category_id
			FROM xf_warning_action_trigger AS action_trigger
			LEFT JOIN xf_warning_action warning_action ON warning_action.warning_action_id = action_trigger.warning_action_id
			WHERE user_id = ?
			ORDER BY trigger_points DESC
		", 'action_trigger_id', $this->user->user_id);
        if ($triggers)
        {
            $remainingTriggers = $triggers;

            foreach ($triggers AS $key => $trigger)
            {
                $categoryId = $trigger['sv_warning_category_id'] ?: 0;
                if (empty($categoryPoints[$categoryId]))
                {
                    // the warning action has been deleted after the warning has been issued, as such the category is now AWOL
                    // dump into global
                    $categoryId = 0;
                }

                $points = $categoryPoints[$categoryId];
                if ($trigger['trigger_points'] > $points->newPoints)
                {
                    unset($remainingTriggers[$key]);
                    $this->removeActionTrigger($trigger, $remainingTriggers);
                }
            }
        }


        if ($fromWarningDelete)
        {
            $actions = $this->getActions('DESC', true);
            /** @var \SV\WarningImprovements\XF\Entity\WarningAction $action */
            foreach ($actions AS $action)
            {
                $categoryId = $action->sv_warning_category_id ?: 0;
                if (empty($categoryPoints[$categoryId]))
                {
                    // the warning action has been deleted after the warning has been issued, as such the category is now AWOL
                    // dump into global
                    $categoryId = 0;
                }

                $points = $categoryPoints[$categoryId];

                // If we're deleting, we need to undo warning action effects, even if they're time limited.
                // Points-based will be handled by the triggers so skip. Then only consider where we cross
                // the points threshold from the old (higher) to the new (lower) point values
                if (
                    $action->action_length_type == 'points'
                    || $action->points > $points->oldPoints // threshold above where we were
                    || $action->points <= $points->newPoints // we're still at/above the threshold
                )
                {
                    continue;
                }

                $this->removeWarningActionEffects($action);
            }
        }
    }
}