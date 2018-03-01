<?php

namespace SV\WarningImprovements\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Member
 */
class Member extends XFCP_Member
{
    public function actionWarningActions(ParameterBag $params)
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $user */
        $user = $this->assertViewableUser($params->user_id);

        if (!$user->canViewWarningActions())
        {
            throw $this->exception($this->noPermission());
        }

        $viewParams = [
            'user' => $user
        ];
        return $this->view('XF:Member\WarningActions', 'sv_member_warning_actions', $viewParams);
    }

    protected function assertViewableUser($userId, array $extraWith = [], $basicProfileOnly = false)
    {
        if ($this->options()->sv_view_own_warnings)
        {
            \SV\WarningImprovements\Globals::$profileUserId = intval($userId);
        }

        return parent::assertViewableUser($userId, $extraWith, $basicProfileOnly);
    }
}