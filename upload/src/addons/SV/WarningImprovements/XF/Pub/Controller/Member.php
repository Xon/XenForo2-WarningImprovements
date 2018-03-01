<?php

namespace SV\WarningImprovements\XF\Pub\Controller;

use SV\WarningImprovements\Globals;
use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Member
 */
class Member extends XFCP_Member
{
    public function actionWarningActions(ParameterBag $params)
    {
        if ($this->filter('warning_action_id', 'uint'))
        {
            return $this->rerouteController(__CLASS__, 'viewWarningAction', $params);
        }

        /** @noinspection PhpUndefinedFieldInspection */
        /** @var \SV\WarningImprovements\XF\Entity\User $user */
        $user = $this->assertViewableUser($params->user_id);

        if (!$user->canViewWarningActions())
        {
            throw $this->exception($this->noPermission());
        }

        $viewParams = [
            'user' => $user
        ];
        return $this->view('XF:Member\WarningActions\List', 'sv_member_warning_actions', $viewParams);
    }

    public function actionViewWarningAction()
    {
        $userChangeTempId = $this->filter('warning_action_id', 'uint');

        $userChangeTemp = $this->assertWarningActionViewable($userChangeTempId);

        $viewParams = [
            'warning_action' => $userChangeTemp
        ];

        return $this->view('XF:Member\WarningActions\View', 'sv_warning_actions_info', $viewParams);
    }

    protected function assertViewableUser($userId, array $extraWith = [], $basicProfileOnly = false)
    {
        if ($this->options()->sv_view_own_warnings)
        {
            Globals::$profileUserId = intval($userId);
        }

        return parent::assertViewableUser($userId, $extraWith, $basicProfileOnly);
    }

    public function assertWarningActionViewable($userChangeTempId, array $extraWith = [])
    {
        /** @var \SV\WarningImprovements\XF\Entity\UserChangeTemp $userChangeTemp */
        $userChangeTemp = $this->em()->find('XF:UserChangeTemp', $userChangeTempId, $extraWith);
        if (!$userChangeTemp)
        {
            /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
            $visitor = \XF::visitor();

            if ($visitor->canViewWarningActions($error))
            {
                throw $this->exception($this->notFound(\XF::phrase('sv_requested_warning_action_not_found')));
            }

            throw $this->exception($this->noPermission($error));
        }

        $canView = $userChangeTemp->canView($error);
        if (!$canView)
        {
            throw $this->exception($this->noPermission($error));
        }

        return $userChangeTemp;
    }
}