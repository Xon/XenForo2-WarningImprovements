<?php

namespace SV\WarningImprovements\XF\Service\Conversation;

class Notifier extends XFCP_Notifier
{
    protected $sv_force_email_for_user_id = null;
    protected $sv_respect_receive_admin_email = true;

    protected function _canUserReceiveNotification(\XF\Entity\User $user, \XF\Entity\User $sender = null)
    {
        if (!empty(\SV\WarningImprovements\Listener::$warnngObj))
        {
            if (\SV\WarningImprovements\Listener::$warnngObj->user_id == $user->user_id)
            {
                if ($this->sv_force_email_for_user_id === null)
                {
                    $this->sv_force_email_for_user_id = 0;
                    $options = $this->app->options();

                    if ($options->sv_force_conversation_email_on_warning)
                    {
                        $this->sv_respect_receive_admin_email = $options->sv_respect_receive_admin_email_on_warning;
                        $this->sv_force_email_for_user_id = \SV\WarningImprovements\Listener::$warnngObj->user_id;
                    }
                    if ($options->sv_only_force_warning_email_on_banned && !$user->is_banned)
                    {
                        $this->sv_force_email_for_user_id = 0;
                    }
                }

                if ($this->sv_force_email_for_user_id)
                {
                    if ($this->sv_respect_receive_admin_email)
                    {
                        $user->Option->email_on_conversation = $user->Option->receive_admin_email;
                    }
                    else
                    {
                        $user->Option->email_on_conversation = true;
                    }

                    $user->is_banned = false;
                }
            }
        }

        return parent::_canUserReceiveNotification($user, $sender);
    }
}