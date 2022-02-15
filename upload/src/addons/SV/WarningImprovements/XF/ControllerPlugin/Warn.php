<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\ControllerPlugin;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\WarningDefinition;
use SV\WarningImprovements\XF\Repository\Warning;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View as ViewReply;
use XF\Warning\AbstractHandler;

/**
 * Extends \XF\ControllerPlugin\Warn
 */
class Warn extends XFCP_Warn
{
    /**
     * @param string $contentType
     * @param Entity $content
     * @param string $warnUrl
     * @param array  $breadcrumbs
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionWarn($contentType, Entity $content, $warnUrl, array $breadcrumbs = [])
    {
        /** @var Warning $warningRepo */
        $warningRepo = $this->repository('XF:Warning');

        if ($this->isPost() && !$this->filter('fill', 'bool'))
        {
            /** @var \XF\Service\FloodCheck $floodChecker */
            $floodChecker = $this->service('XF:FloodCheck');
            $timeRemaining = $floodChecker->checkFlooding('warn.'.$contentType, $content->getEntityId(), 5);
            if ($timeRemaining)
            {
                throw $this->exception($this->controller->error(\XF::phrase('must_wait_x_seconds_before_performing_this_action', ['count' => $timeRemaining])));
            }
        }

        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        $warnings = $visitor->getUsableWarningDefinitions();

        if (empty($warnings))
        {
            return $this->error(\XF::phrase('sv_no_permission_to_give_warnings'), 403);
        }

        $response = parent::actionWarn($contentType, $content, $warnUrl, $breadcrumbs);

        if ($response instanceof Redirect)
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
            $categoryRepo = $this->getWarningCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree();

            /** @var \SV\WarningImprovements\XF\Entity\User $user */
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

            $warningLimit = (int)(\XF::options()->svPreviousWarningLimit ?? -1);
            if ($canViewPreviousWarnings && $user !== null && $warningLimit >= 0)
            {
                $warningList = $warningRepo->findUserWarningsForList($user->user_id);
                if ($warningLimit > 0)
                {
                    $warningList->limit($warningLimit);
                }
                $previousWarnings = $warningList->fetch()
                                                ->filterViewable()
                                                ->toArray();
            }
            if ($warningLimit < 0)
            {
                $canViewPreviousWarnings = false;
            }

            $warningStructure = $this->app->em()->getEntityStructure('XF:Warning');
            $nodeColDefinition = $warningStructure->columns['notes'] ?? null;
            $userNoteRequired = \is_array($nodeColDefinition) && (!isset($nodeColDefinition['default']) || !empty($nodeColDefinition['required']));
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
     * @param AbstractHandler                             $warningHandler
     * @param \SV\WarningImprovements\XF\Entity\User|User $user
     * @param string                                      $contentType
     * @param Entity                                      $content
     * @param array                                       $input
     * @return ViewReply
     */
    protected function getWarningFillerReply(AbstractHandler $warningHandler, User $user, $contentType, Entity $content, array $input)
    {
        $response = parent::getWarningFillerReply($warningHandler, $user, $contentType, $content, $input);

        if ($response instanceof ViewReply)
        {
            $response->setParam('user', $user);
            $response->setParam('content', $content);

            /** @var Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');
            /** @var WarningDefinition|null $definition */
            $definition = $response->getParam('definition');

            if ($definition === null || $input['warning_definition_id'] === 0)
            {
                $definition = $warningRepo->getCustomWarningDefinition();
                $response->setParam('definition', $definition);
            }

            if ($definition->is_custom ?? false)
            {
                list($conversationTitle, $conversationMessage) = $definition->getSpecificConversationContent(
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
     * @param User            $user
     * @param string          $contentType
     * @param Entity          $content
     * @param array           $input
     * @return \SV\WarningImprovements\XF\Service\User\Warn|\XF\Service\User\Warn
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupWarnService(AbstractHandler $warningHandler, User $user, $contentType, Entity $content, array $input)
    {
        Globals::$warningInput = $input;
        try
        {
            return parent::setupWarnService($warningHandler, $user, $contentType, $content, $input);
        }
        finally
        {
            Globals::$warningInput = null;
        }
    }

    /**
     * @return \SV\WarningImprovements\Repository\WarningCategory|\XF\Mvc\Entity\Repository
     */
    protected function getWarningCategoryRepo()
    {
        return $this->repository('SV\WarningImprovements:WarningCategory');
    }
}