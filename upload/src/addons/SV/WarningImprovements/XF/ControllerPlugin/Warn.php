<?php

namespace SV\WarningImprovements\XF\ControllerPlugin;

use XF\Mvc\Entity\Entity;

/**
 * Extends \XF\ControllerPlugin\Warn
 */
class Warn extends XFCP_Warn
{
    public function actionWarn($contentType, Entity $content, $warnUrl, array $breadcrumbs = [])
    {
        $response = parent::actionWarn($contentType, $content, $warnUrl, $breadcrumbs);

        if ($response instanceof \XF\Mvc\Reply\View)
        {
            $categoryRepo = $this->getWarningCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree(null, 0, true);

            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');
            $warnings = $warningRepo->findWarningDefinitionsForListGroupedByCategory();

            $response->setParams([
                'warnings' => $warnings,
                'categoryTree' => $categoryTree
            ]);
        }

        return $response;
    }

	protected function setupWarnService(\XF\Warning\AbstractHandler $warningHandler, \XF\Entity\User $user, $contentType, \XF\Mvc\Entity\Entity $content, array $input)
	{
		$options = $this->app->options();

		if (!$input['notes'] && $options->sv_wi_require_warning_notes)
		{
			throw $this->exception(
				$this->error(\XF::phrase('sv_please_enter_note_for_warning'))
			);
		}
		
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