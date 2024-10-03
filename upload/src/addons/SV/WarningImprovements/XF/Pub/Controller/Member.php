<?php

namespace SV\WarningImprovements\XF\Pub\Controller;

use SV\StandardLib\Helper;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\User as ExtendedUserEntity;
use SV\WarningImprovements\XF\Entity\UserChangeTemp as ExtendedUserChangeTempEntity;
use XF\Entity\UserChangeTemp as UserChangeTempEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Service\User\TempChange as TempChangeService;
use function pow;
use function strtotime;

/**
 * @extends \XF\Pub\Controller\Member
 */
class Member extends XFCP_Member
{
    public function actionWarnings(ParameterBag $params)
    {
        $reply = parent::actionWarnings($params);

        if ($reply instanceof ViewReply)
        {
            /** @var AbstractCollection $warnings */
            $warnings = $reply->getParam('warnings');
            if ($warnings)
            {
                $warnings = $warnings->filterViewable();
                $reply->setParam('warnings', $warnings);

                $ageLimit = (int)(\XF::options()->svWarningsOnProfileAgeLimit ?? 0);
                if ($ageLimit > 0)
                {
                    $oldCount = 0;
                    /** @var \SV\WarningImprovements\XF\Entity\Warning $warning */
                    foreach ($warnings as $warning)
                    {
                        if ($warning->is_old_warning)
                        {
                            $oldCount += 1;
                        }
                    }
                    $reply->setParam('oldWarningCount', $oldCount);
                }
            }
        }

        return $reply;
    }

    public function actionWarningActions(ParameterBag $params): AbstractReply
    {
        if ($this->filter('warning_action_id', 'uint'))
        {
            return $this->rerouteController(__CLASS__, 'viewWarningAction', $params);
        }

        /** @noinspection PhpUndefinedFieldInspection */
        /** @var ExtendedUserEntity $user */
        $user = $this->assertViewableUser((int)$params->user_id);

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

    public function actionViewWarningAction(ParameterBag $params): AbstractReply
    {
        $userChangeTempId = $this->filter('warning_action_id', 'uint');

        /** @noinspection PhpUndefinedFieldInspection */
        /** @var ExtendedUserEntity $user */
        $user = $this->assertViewableUser((int)$params->user_id);

        $userChangeTemp = $this->assertWarningActionViewable($userChangeTempId);

        $viewParams = [
            'user' => $user,

            'warningAction' => $userChangeTemp
        ];

        return $this->view('XF:Member\WarningActions\View', 'sv_warning_actions_info', $viewParams);
    }

    public function actionWarningActionsExpire(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $userChangeTempId = $this->filter('warning_action_id', 'uint');

        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertViewableUser((int)$params->user_id);

        $userChangeTemp = $this->assertWarningActionViewable($userChangeTempId);

        if (!$userChangeTemp->canEditWarningAction($error))
        {
            throw $this->exception($this->noPermission($error));
        }

        if ($this->filter('expire', 'str') === 'now')
        {
            $expiryDate = \XF::$time;
        }
        else
        {
            $expiryLength = $this->filter('expiry_value', 'uint');
            $expiryUnit = $this->filter('expiry_unit', 'str');

            $expiryDate = @strtotime("+$expiryLength $expiryUnit");
            if ($expiryDate === false)
            {
                $userChangeTemp->error(\XF::phrase('svWarningImprovements_invalid_offset_date', [
                    'expiryLength' => $expiryLength,
                    'expiryUnit'   => $expiryUnit,
                ]), 'expiry_date');
            }
            $expiryDate = (int)$expiryDate;
            if ($expiryDate >= pow(2, 32) - 1)
            {
                $expiryDate = 0;
            }
        }

        $db = \XF::db();
        $db->beginTransaction();

        $userChangeTemp->expiry_date = $expiryDate;
        $userChangeTemp->save(true, false);

        if ($userChangeTemp->is_expired)
        {
            $changeService = Helper::service(TempChangeService::class);
            $changeService->expireChange($userChangeTemp);
        }

        $db->commit();

        return $this->redirect($this->getDynamicRedirect());
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function assertViewableUser($userId, array $extraWith = [], $basicProfileOnly = false)
    {
        if ($this->options()->sv_view_own_warnings ?? false)
        {
            Globals::$profileUserId = (int)$userId;
        }

        return parent::assertViewableUser($userId, $extraWith, $basicProfileOnly);
    }

    public function assertWarningActionViewable(?int $userChangeTempId, array $extraWith = []): ExtendedUserChangeTempEntity
    {
        /** @var ExtendedUserChangeTempEntity|null $userChangeTemp */
        $userChangeTemp = Helper::find(UserChangeTempEntity::class, $userChangeTempId, $extraWith);
        if ($userChangeTemp === null)
        {
            /** @var ExtendedUserEntity $visitor */
            $visitor = \XF::visitor();

            if ($visitor->canViewWarningActions($error))
            {
                throw $this->exception($this->notFound(\XF::phrase('sv_requested_warning_action_not_found')));
            }

            throw $this->exception($this->noPermission($error));
        }

        $canView = $userChangeTemp->canViewWarningAction($error);
        if (!$canView)
        {
            throw $this->exception($this->noPermission($error));
        }

        return $userChangeTemp;
    }
}