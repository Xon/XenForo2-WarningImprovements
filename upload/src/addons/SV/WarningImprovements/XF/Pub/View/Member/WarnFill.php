<?php

namespace SV\WarningImprovements\XF\Pub\View\Member;

/**
 * Extends \XF\Pub\View\Member\WarnFill
 */
class WarnFill extends XFCP_WarnFill
{
    public function renderJson()
    {
        $response = parent::renderJson();

        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $warningDefinition */
        $warningDefinition = $this->params['definition'];
        $options = \XF::app()->options();

        if ($options->sv_warningimprovements_conversation_locked)
        {
            $response['formValues']['input[name=conversation_locked]'] = true;
        }

        if ($options->sv_warningimprovements_conversation_send_default)
        {
            $response['formValues']['#startConversation'] = false;
        }

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

        if ($warningDefinition->sv_custom_title)
        {
            $response['formValues']['#customTitle'] = true;
        }

        return $response;
    }
}