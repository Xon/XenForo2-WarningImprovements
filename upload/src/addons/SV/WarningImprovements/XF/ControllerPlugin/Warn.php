<?php

namespace SV\WarningImprovements\XF\ControllerPlugin;

use XF\Mvc\Entity\Entity;

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
     *
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionWarn($contentType, Entity $content, $warnUrl, array $breadcrumbs = [])
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->repository('XF:Warning');
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        $warnings = $visitor->getUsableWarningDefinitions();

        if (empty($warnings))
        {
            return $this->error(\XF::phrase('sv_no_permission_to_give_warnings'), 403);
        }

        $response = parent::actionWarn($contentType, $content, $warnUrl, $breadcrumbs);

        if ($response instanceof \XF\Mvc\Reply\Redirect)
        {
            if (empty($response->getMessage()))
            {
                $response->setMessage(\XF::phrase('sv_issued_warning'));
            }

            return $response;
        }
        else if ($response instanceof \XF\Mvc\Reply\View)
        {
            $categoryRepo = $this->getWarningCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree(null, 0, true);

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
     * @param \XF\Warning\AbstractHandler $warningHandler
     * @param \SV\WarningImprovements\XF\Entity\User|\XF\Entity\User $user
     * @param $contentType
     * @param Entity $content
     * @param array $input
     *
     * @return \XF\Mvc\Reply\View
     */
    protected function getWarningFillerReply(
        \XF\Warning\AbstractHandler $warningHandler,
        \XF\Entity\User $user,
        $contentType,
        \XF\Mvc\Entity\Entity $content,
        array $input
    )
    {
        $response = parent::getWarningFillerReply($warningHandler, $user, $contentType, $content, $input);

        if ($response instanceof \XF\Mvc\Reply\View && $input['warning_definition_id'] === 0)
        {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');

            /** @var \XF\Entity\WarningDefinition $definition */
            $definition = $warningRepo->getCustomWarning();

            if ($definition)
            {
                list($conversationTitle, $conversationMessage) = $definition->getSpecificConversationContent(
                    $user, $contentType, $content
                );
            }
            else
            {
                $conversationTitle = '';
                $conversationMessage = '';
            }

            $response->setParams([
                'definition' => $definition,
                'conversationTitle' => $conversationTitle,
                'conversationMessage' => $conversationMessage
            ]);
        }

        return $response;
    }

    /**
     * @param \XF\Warning\AbstractHandler $warningHandler
     * @param \XF\Entity\User $user
     * @param string $contentType
     * @param Entity $content
     * @param array $input
     *
     * @return \SV\WarningImprovements\XF\Service\User\Warn|\XF\Service\User\Warn
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupWarnService(\XF\Warning\AbstractHandler $warningHandler, \XF\Entity\User $user, $contentType, \XF\Mvc\Entity\Entity $content, array $input)
	{
	    \SV\WarningImprovements\Listener::$warningInput = $input;
		return parent::setupWarnService($warningHandler, $user, $contentType, $content, $input);
	}

    /**
     * @return \SV\WarningImprovements\Repository\WarningCategory
     */
    protected function getWarningCategoryRepo()
    {
        return $this->repository('SV\WarningImprovements:WarningCategory');
    }
}