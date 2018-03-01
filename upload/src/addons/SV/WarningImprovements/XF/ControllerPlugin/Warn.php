<?php

namespace SV\WarningImprovements\XF\ControllerPlugin;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\WarningDefinition;
use SV\WarningImprovements\XF\Repository\Warning;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;
use XF\Warning\AbstractHandler;

/**
 * Extends \XF\ControllerPlugin\Warn
 */
class Warn extends XFCP_Warn
{
    /**
     * @param $contentType
     * @param Entity $content
     * @param $warnUrl
     * @param array $breadcrumbs
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|Redirect|View
     */
    public function actionWarn($contentType, Entity $content, $warnUrl, array $breadcrumbs = [])
    {
        /** @var Warning $warningRepo */
        $warningRepo = $this->repository('XF:Warning');
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

            return $response;
        }
        else if ($response instanceof View)
        {
            $categoryRepo = $this->getWarningCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree();

            /** @var \SV\WarningImprovements\XF\Entity\User $user */
            $user = $response->getParam('user');
            $previousWarnings = null;

            if ($user)
            {
                $previousWarnings = $warningRepo->findUserWarningsForList($user->user_id)->limit(5); // make this a option?
            }

            $response->setParams([
                'warnings' => $warnings,
                'previousWarnings' => $previousWarnings,

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
     * @return View
     */
    protected function getWarningFillerReply(
        AbstractHandler $warningHandler,
        User $user,
        $contentType,
        Entity $content,
        array $input
    )
    {
        $response = parent::getWarningFillerReply($warningHandler, $user, $contentType, $content, $input);

        if ($response instanceof View)
        {
            /** @var Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');
            /** @var WarningDefinition $definition */
            $definition = $response->getParam('definition');

            if (!$definition || $input['warning_definition_id'] === 0)
            {
                $definition = $warningRepo->getCustomWarning();
                $response->setParam('definition', $definition);
            }

            if ($definition && $definition->is_custom)
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

        return $return;
    }

    /**
     * @param AbstractHandler $warningHandler
     * @param User $user
     * @param string $contentType
     * @param Entity $content
     * @param array $input
     *
     * @return \SV\WarningImprovements\XF\Service\User\Warn|\XF\Service\User\Warn
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupWarnService(AbstractHandler $warningHandler, User $user, $contentType, Entity $content, array $input)
	{
	    Globals::$warningInput = $input;
		return parent::setupWarnService($warningHandler, $user, $contentType, $content, $input);
	}

    /**
     * @return \SV\WarningImprovements\Repository\WarningCategory|\XF\Mvc\Entity\Repository
     */
    protected function getWarningCategoryRepo()
    {
        return $this->repository('SV\WarningImprovements:WarningCategory');
    }
}