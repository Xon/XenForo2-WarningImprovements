<?php

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Entity\WarningCategory;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\User;
use SV\WarningImprovements\XF\Entity\Warning;
use XF\Entity\WarningAction;
use XF\Mvc\Entity\AbstractCollection;

class WarningTotal
{
    /** @var int */
    public $categoryId;
    /** @var int */
    public $newCount;
    /** @var int */
    public $newPoints;
    /** @var int */
    public $oldCount;
    /** @var int */
    public $oldPoints;

    public function __construct($categoryId, $newPoints = 0, $newCount = 0, $oldPoints = 0, $oldCount = 0)
    {
        $this->categoryId = $categoryId;
        $this->newPoints = $newPoints;
        $this->newCount = $newCount;
        $this->oldPoints = $oldPoints;
        $this->oldCount = $oldCount;
    }

    /**
     * @param int $oldPoints
     */
    public function addOld($oldPoints)
    {
        $this->oldPoints += $oldPoints;
        $this->oldCount += 1;
    }

    /**
     * @param int $newPoints
     */
    public function addNew($newPoints)
    {
        $this->newPoints += $newPoints;
        $this->newCount += 1;
    }

    /**
     * @param WarningTotal $warningTotal
     */
    public function addTotals(WarningTotal $warningTotal)
    {
        $this->oldPoints += $warningTotal->oldPoints;
        $this->oldCount += $warningTotal->oldCount;
        $this->newPoints += $warningTotal->newPoints;
        $this->newCount += $warningTotal->newCount;
    }
}

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

    public function __construct(\XF\App $app, User $user)
    {
        parent::__construct($app, $user);

        $this->setWarning(Globals::$warningObj);
    }

    /**
     * @param Warning $warning
     */
    public function setWarning(Warning $warning)
    {
        $this->warning = $warning;
    }

    protected function applyWarningAction(WarningAction $action)
    {
        parent::applyWarningAction($action);

        if ($this->warning)
        {
            if ((empty($this->lastWarningAction) || $action->points > $this->lastWarningAction->points) && (!empty($action->sv_post_node_id) || !empty($action->sv_post_thread_id)))
            {
                $this->lastAction = $action;
            }
        }
    }

    /**
     * Must be ordered from parent to child!
     *
     * @var WarningCategory[]
     */
    protected $warningCategories = [];

    /**
     * @return AbstractCollection|null
     */
    protected function getActions()
    {
        $actions = null;

        if ($this->warning)
        {
            /** @var \SV\WarningImprovements\Repository\WarningCategory $warningCategoryRepo */
            $warningCategoryRepo = $this->repository('SV\WarningImprovements:WarningCategory');

            $category = $this->warning->Definition->Category;
            $categories = $warningCategoryRepo->findCategoryParentList($category)->fetch();
            // Must be ordered from least specific category to most specific (ie root => leaf)
            $this->warningCategories = $categories->toArray();
            $this->warningCategories[$category->warning_category_id] = $category;

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

            $categoryIds[] = $category->warning_category_id;

            $actions = $actions->where('sv_warning_category_id', $categoryIds)->fetch();
        }

        return $actions;
    }

    /**
     * @param bool $removePoints
     * @return WarningTotal[]
     */
    protected function getCategoryPoints($removePoints = false)
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = \XF::repository('XF:Warning');
        /** @var Warning[] $warnings */
        $warnings = $warningRepo->findUserWarningsForList($this->user->user_id)
                                ->where('is_expired', '=', 0)
                                ->fetch();

        $oldWarning = null;
        $newWarning = null;
        if ($removePoints)
        {
            $oldWarning = $this->warning;
            $newWarning = null;
        }
        else
        {
            $newWarning = $this->warning;
            $oldWarning = null;
        }
        $warningPoints = [];

        $warningTotalsCumulative = [0 => new WarningTotal(0)];
        /** @var WarningTotal $globalTotals */
        $globalTotals = $warningTotalsCumulative[0];

        // compute global and per-category totals
        foreach ($warnings as $warning)
        {
            $categoryId = $warning->Definition->sv_warning_category_id;
            if (empty($warningPoints[$categoryId]))
            {
                $warningPoints[$categoryId] = new WarningTotal($categoryId);
            }
            /** @var WarningTotal $warningTotal */
            $warningTotal = $warningPoints[$categoryId];

            if ($newWarning === null ||
                $warning->warning_id != $newWarning->warning_id)
            {
                $globalTotals->addOld($warning->points);

                if ($categoryId)
                {
                    $warningTotal->addOld($warning->points);
                }
            }
            if ($oldWarning === null ||
                $warning->warning_id != $oldWarning->warning_id)
            {
                $globalTotals->addNew($warning->points);

                if ($categoryId)
                {
                    $warningTotal->addNew($warning->points);
                }
            }
        }

        // propagate totals from child categories to parent, relying on the order to remove the need for recursion
        foreach ($this->warningCategories as $categoryId => $category)
        {
            if (empty($warningPoints[$categoryId]))
            {
                $warningPoints[$categoryId] = new WarningTotal($categoryId);
            }
            $parentId = $category->parent_category_id;
            if ($parentId)
            {
                /** @var WarningTotal $warningTotal */
                $warningTotals = $warningPoints[$categoryId];

                /** @var WarningTotal $parentTotals */
                $parentTotals = $warningPoints[$parentId];

                $parentTotals->addTotals($warningTotals);
            }
        }

        /** @var WarningTotal[] $warningPointsCumulative */
        return $warningTotalsCumulative;
    }

    protected function processPointsIncrease($oldPoints, $newPoints)
    {
        if ($this->warning)
        {
            parent::processPointsIncrease($oldPoints, $newPoints);

            return;
        }

        $actions = $this->getActions();

        if (empty($actions))
        {
            return;
        }

        $categoryPoints = $this->getCategoryPoints(false);

        /** @var \XF\Entity\WarningAction $action */
        foreach ($actions AS $action)
        {
            $warningCategoryId = $action->warning_action_id ?: 0;

            $points = isset($categoryPoints[$warningCategoryId])
                ? $categoryPoints[$warningCategoryId]
                : new WarningTotal($warningCategoryId, $newPoints,1, $oldPoints, 0);

            if ($action->points > $points->oldPoints && $action->points <= $points->newPoints)
            {
                $this->applyWarningAction($action);
            }
        }

        $this->warningActionNotifications();
    }

    public function  warningActionNotifications()
    {
        if (!empty($this->lastAction))
        {
            $postAsUserId = empty($this->lastAction->sv_post_as_user_id) ? $this->warning->user_id : $this->lastAction->sv_post_as_user_id;

            /** @var User $postAsUser */
            $postAsUser = $this->em()->find('XF:User', $postAsUserId);

            if (!empty($postAsUser))
            {
                $dateString = date($this->app->options()->sv_warning_date_format, \XF::$time);

                $params = [
                    'username' => $this->user->username,
                    'points' =>  $this->user->warning_count,
                    'warning' => $this->warning,
                    'date' => $dateString,
                    'warning_title' => $this->warning->title,
                    'warning_points' => $this->warning->points,
                    'warning_category' => $this->warning->Definition->Category,
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

                        /** @noinspection PhpExpressionResultUnusedInspection */
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

                        /** @noinspection PhpExpressionResultUnusedInspection */
                        $threadReplier;
                    }
                }
            }
        }
    }

    protected function processPointsDecrease($oldPoints, $newPoints, $fromWarningDelete = false)
    {
        if (!$this->warning || !$fromWarningDelete)
        {
            parent::processPointsDecrease($oldPoints, $newPoints, $fromWarningDelete);
            return;
        }

        parent::processPointsDecrease($oldPoints, $newPoints, false);

        $actions = $this->getActions();

        if (empty($actions))
        {
            return;
        }

        // $newPoints are uncategorized, $this->>warning should be set to the warning which inflicted the changes
        // getCategoryPoints will use this to work out the actual warning points.
        $categoryPoints = $this->getCategoryPoints(true);

        /** @var \XF\Entity\WarningAction $action */
        foreach ($actions AS $action)
        {
            $warningCategoryId = $action->warning_action_id ?: 0;

            $points = isset($categoryPoints[$warningCategoryId])
                ? $categoryPoints[$warningCategoryId]
                : new WarningTotal($warningCategoryId, $newPoints,1, $oldPoints, 0);

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