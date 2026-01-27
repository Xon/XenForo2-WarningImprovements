<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\ControllerPlugin;

use SV\StandardLib\Helper;
use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsWrappingContentWithSpoilerInterface;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\Repository\WarningCategory as WarningCategoryRepo;
use SV\WarningImprovements\XF\Entity\User as ExtendedUserEntity;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use SV\WarningImprovements\XF\Finder\Warning as ExtendedWarningFinder;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Entity\User as UserEntity;
use XF\Entity\Warning as WarningEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect as RedirectReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Repository\Warning as WarningRepo;
use XF\Service\FloodCheck as FloodCheckService;
use XF\Warning\AbstractHandler;
use SV\WarningImprovements\XF\Service\User\Warn as ExtendedUserWarnSvc;
use function is_array;

/**
 * @extends \XF\ControllerPlugin\Warn
 */
class Warn extends XFCP_Warn
{
    public function actionWarn($contentType, Entity $content, $warnUrl, array $breadcrumbs = [])
    {
        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = Helper::repository(WarningRepo::class);

        if ($this->isPost() && (!$this->filter('fill', 'bool') && !$this->filter('sv_save_warn_view_pref', 'bool')))
        {
            $floodChecker = Helper::service(FloodCheckService::class);
            $timeRemaining = $floodChecker->checkFlooding('warn.'.$contentType, $content->getEntityId(), 5);
            if ($timeRemaining)
            {
                throw $this->exception($this->controller->error(\XF::phrase('must_wait_x_seconds_before_performing_this_action', ['count' => $timeRemaining])));
            }
        }

        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        $warnings = $visitor->getUsableWarningDefinitions();

        if (empty($warnings))
        {
            return $this->error(\XF::phrase('sv_no_permission_to_give_warnings'), 403);
        }

        if ($this->isPost() && $this->filter('sv_save_warn_view_pref', 'bool'))
        {
            return $this->getSvSaveWarningViewPrefReply($content, $contentType);
        }

        $response = parent::actionWarn($contentType, $content, $warnUrl, $breadcrumbs);

        if ($response instanceof RedirectReply)
        {
            if (empty($response->getMessage()))
            {
                $response->setMessage(\XF::phrase('sv_issued_warning'));
            }

            if (\XF::options()->svWarningRedirect ?? false)
            {
                $warningHandler = $warningRepo->getWarningHandler($contentType, true);
                if ($warningHandler->canViewContent($content) &&
                    ($url = $warningHandler->getContentUrl($content)))
                {
                    $response->setUrl($url);
                }
            }

            return $response;
        }
        else if ($response instanceof ViewReply)
        {
            $response->setParam('content', $content);
            $response->setParam('disablingReactionsAction', $content instanceof SupportsDisablingReactionInterface);
            $response->setParam('spoilerContentsAction', $content instanceof SupportsWrappingContentWithSpoilerInterface);

            $categoryRepo = $this->getWarningCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree();

            /** @var ExtendedUserEntity $user */
            $user = $response->getParam('user');
            $previousWarnings = [];

            //if ($user !== null && (\XF::options()->sv_view_own_warnings ?? false))
            if (\XF::options()->sv_view_own_warnings ?? false)
            {
                Globals::$profileUserId = $user->user_id ?: null;
            }
            try
            {
                $canViewPreviousWarnings = $visitor->canViewWarnings();
            }
            finally
            {
                Globals::$profileUserId = null;
            }

            $warningLimit = \XF::options()->svPreviousWarningLimit ?? -1;
            if ($canViewPreviousWarnings && $user !== null && $warningLimit >= 0)
            {
                /** @var ExtendedWarningFinder $warningList */
                $warningList = $warningRepo->findUserWarningsForList($user->user_id);
                if ($warningLimit > 0)
                {
                    $warningList->limit($warningLimit);
                }
                $ageLimit = \XF::options()->svWarningsOnProfileAgeLimit ?? 0;
                $warningList->withAgeLimit($ageLimit);

                $previousWarnings = $warningList->fetch()
                                                ->filterViewable()
                                                ->toArray();
            }
            if ($warningLimit < 0)
            {
                $canViewPreviousWarnings = false;
            }

            $warningStructure = Helper::getEntityStructure(WarningEntity::class);
            $nodeColDefinition = $warningStructure->columns['notes'] ?? null;
            $userNoteRequired = is_array($nodeColDefinition) && (!isset($nodeColDefinition['default']) || !empty($nodeColDefinition['required']));
            $response->setParams(
                [
                    'userNoteRequired' => $userNoteRequired,
                    'warnings'         => $warnings,
                    'previousWarnings' => $previousWarnings,
                    'canViewPreviousWarnings' => $canViewPreviousWarnings,

                    'categoryTree' => $categoryTree
                ]);
        }

        return $response;
    }


    /**
     * @since 2.10.2
     */
    public function getSvSaveWarningViewPrefReply(
        Entity $content,
        ?string $contentType = null,
        ?UserEntity $forUser = null
    ) : AbstractReply
    {
        $this->assertPostOnly();

        $contentType = $contentType ?? $content->getEntityContentType();
        $forUser = $forUser ?? \XF::visitor();

        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = Helper::repository(WarningRepo::class);

        $warningHandler = $warningRepo->getWarningHandler($contentType, true);
        if (!$warningHandler)
        {
            throw $this->exception($this->noPermission());
        }

        $user = $warningHandler->getContentUser($content);
        if (!$user)
        {
            throw $this->exception($this->noPermission());
        }

        $value = $this->filter('view', 'str');
        if (!in_array($value, ['radio', 'select']))
        {
            throw $this->exception($this->noPermission()); // Just fail without giving too much details
        }

        $userOption = $forUser->getRelationOrDefault('Option');
        $this->formAction()->basicEntitySave($userOption, [
            'sv_warning_view' => $value
        ])->run();

        return $this->message(\XF::phrase('action_completed_successfully'));
    }

    /**
     * @param AbstractHandler               $warningHandler
     * @param ExtendedUserEntity|UserEntity $user
     * @param string                        $contentType
     * @param Entity                        $content
     * @param array                         $input
     * @return ViewReply
     */
    protected function getWarningFillerReply(AbstractHandler $warningHandler, UserEntity $user, $contentType, Entity $content, array $input)
    {
        $response = parent::getWarningFillerReply($warningHandler, $user, $contentType, $content, $input);

        if ($response instanceof ViewReply)
        {
            $response->setParam('user', $user);
            $response->setParam('content', $content);

            /** @var ExtendedWarningRepo $warningRepo */
            $warningRepo = Helper::repository(WarningRepo::class);
            /** @var ExtendedWarningDefinitionEntity|null $definition */
            $definition = $response->getParam('definition');

            if ($definition === null || $input['warning_definition_id'] === 0)
            {
                $definition = $warningRepo->getCustomWarningDefinition();
                $response->setParam('definition', $definition);
            }

            if ($definition->is_custom ?? false)
            {
                [$conversationTitle, $conversationMessage] = $definition->getSpecificConversationContent(
                    $user, $contentType, $content
                );

                $response->setParams(
                    [
                        'conversationTitle'   => $conversationTitle,
                        'conversationMessage' => $conversationMessage
                    ]);
            }

            if ($definition)
            {
                $newDefinition = $warningRepo->escalateDefaultExpirySettingsForUser($user, $definition);
                $response->setParam('definition', $newDefinition);
            }
        }

        return $response;
    }

    /**
     * @return array
     */
    protected function getWarnSubmitInput()
    {
        $return = parent::getWarnSubmitInput();

        $return['send_warning_alert'] = $this->filter('send_warning_alert', 'bool');
        $return['send_warning_alert_reason'] = $this->filter('send_warning_alert_reason', 'str');

        return $return;
    }

    /**
     * @param AbstractHandler $warningHandler
     * @param UserEntity      $user
     * @param string          $contentType
     * @param Entity          $content
     * @param array           $input
     * @return ExtendedUserWarnSvc|\XF\Service\User\Warn
     */
    protected function setupWarnService(AbstractHandler $warningHandler, UserEntity $user, $contentType, Entity $content, array $input)
    {
        Globals::$warningInput = $input;
        try
        {
            /** @var ExtendedUserWarnSvc $warnService */
            $warnService = parent::setupWarnService($warningHandler, $user, $contentType, $content, $input);

            if ($this->filter('sv_spoiler_contents', 'bool'))
            {
                $warnService->setContentSpoilerTitleForSvWarnImprove($this->filter('sv_content_spoiler_title', 'str'));
            }

            if ($this->filter('sv_disable_reactions', 'bool'))
            {
                $warnService->disableReactionsForSvWarnImprov();
            }

            return $warnService;
        }
        finally
        {
            Globals::$warningInput = null;
        }
    }

    protected function getWarningCategoryRepo(): WarningCategoryRepo
    {
        return Helper::repository(WarningCategoryRepo::class);
    }
}