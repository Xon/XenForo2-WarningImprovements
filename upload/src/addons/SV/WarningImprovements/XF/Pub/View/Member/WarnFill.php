<?php

namespace SV\WarningImprovements\XF\Pub\View\Member;

use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;

/**
 * @extends \XF\Pub\View\Member\WarnFill
 */
class WarnFill extends XFCP_WarnFill
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function renderJson()
    {
        $response = parent::renderJson();

        /** @var ExtendedWarningDefinitionEntity $warningDefinition */
        $warningDefinition = $this->params['definition'];
        $options = \XF::app()->options();

        // note; casting to bool is required!
        $response['formValues']['input[name=conversation_locked]'] = (bool)($options->sv_warningimprovements_conversation_locked ?? false);
        $response['formValues']['input[name=start_conversation]'] = (bool)($options->sv_warningimprovements_conversation_send_default ?? false);
        $response['formValues']['input[name=open_invite]'] = (bool)($options->sv_warningimprovements_conversation_invite ?? false);
        $response['formValues']['input[name=send_warning_alert]'] = (bool)($options->sv_warningimprovements_alert_send_default ?? false);

/*
XF bug; https://xenforo.com/community/threads/form-filler-doesnt-work-well-with-disabler.143576
Use a template mopdification which calls $user.getWarningDefaultContentAction() instead of this:
        switch ($options->sv_warningimprovements_default_content_action)
        {
            case 'delete_content':
                $response['formValues']['input[name=content_action][value="delete"]'] = 1;
                break;
            case 'public_warning':
                $response['formValues']['input[name=content_action][value="public"]'] = 1;
                break;
            case 'none';
            default:
                $response['formValues']['input[name=content_action][value=""]'] = 1;
                break;
        }
*/
        if ($warningDefinition->sv_custom_title)
        {
            $response['formValues']['#customTitle'] = true;
        }

        $response['formValues']['input[name=sv_spoiler_contents]'] = $warningDefinition->sv_spoiler_contents;
        $response['formValues']['input[name=sv_content_spoiler_title]'] = $warningDefinition->sv_content_spoiler_title
            ->render('html', [
                'nameOnInvalid' => false,
            ]);
        $response['formValues']['input[name=sv_disable_reactions]'] = $warningDefinition->sv_disable_reactions;

        return $response;
    }
}