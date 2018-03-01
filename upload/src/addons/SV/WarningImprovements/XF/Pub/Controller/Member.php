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

        if ($user->warning_actions_count === 0)
        {
            return $this->message(\XF::phrase('this_member_has_no_warning_actions_available'));
        }

        $viewParams = [
            'user' => $user
        ];
        return $this->view('XF:Member\WarningActions\List', 'sv_member_warning_actions', $viewParams);
    }

    public function actionViewWarningAction(ParameterBag $params)
    {
        $userChangeTempId = $this->filter('warning_action_id', 'uint');

        /** @noinspection PhpUndefinedFieldInspection */
        /** @var \SV\WarningImprovements\XF\Entity\User $user */
        $user = $this->assertViewableUser($params->user_id);

        $userChangeTemp = $this->assertWarningActionViewable($userChangeTempId);

        $viewParams = [
            'user' => $user,

            'warningAction' => $userChangeTemp
        ];

        return $this->view('XF:Member\WarningActions\View', 'sv_warning_actions_info', $viewParams);
    }

    public function actionWarningActionsExpire(ParameterBag $params)
    {
        $this->assertPostOnly();
        $userChangeTempId = $this->filter('warning_action_id', 'uint');

        /** @noinspection PhpUndefinedFieldInspection */
        /** @var \SV\WarningImprovements\XF\Entity\User $user */
        $user = $this->assertViewableUser($params->user_id);

        $userChangeTemp = $this->assertWarningActionViewable($userChangeTempId);

        if (!$userChangeTemp->canEditWarningActions($error))
        {
            throw $this->exception($this->noPermission($error));
        }

        if ($this->filter('expire', 'str') == 'now')
        {
            $expiryDate = \XF::$time;
        }
        else
        {
            $expiryLength = $this->filter('expiry_value', 'uint');
            $expiryUnit = $this->filter('expiry_unit', 'str');

            $expiryDate = strtotime("+$expiryLength $expiryUnit");
            if ($expiryDate >= pow(2, 32) - 1)
            {
                $expiryDate = 0;
            }
        }

        $userChangeTemp->expiry_date = $expiryDate;
        $userChangeTemp->save();

        return $this->redirect($this->getDynamicRedirect());
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