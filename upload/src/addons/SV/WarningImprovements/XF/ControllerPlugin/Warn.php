<?php

namespace SV\WarningImprovements\XF\ControllerPlugin;

/**
 * Extends \XF\ControllerPlugin\Warn
 */
class Warn extends XFCP_Warn
{
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
}