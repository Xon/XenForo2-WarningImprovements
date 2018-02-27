<?php

namespace SV\WarningImprovements\XF\Service\Conversation;

class Notifier extends XFCP_Notifier
{
    protected function _canUserReceiveNotification(\XF\Entity\User $user, \XF\Entity\User $sender = null)
    {
        if (!empty(\SV\WarningImprovements\Listener::$warnngObj))
        {
            if (\SV\WarningImprovements\Listener::$warnngObj->user_id == $user->user_id)
            {
                $options = $this->app->options();

                $this->conversation->setOption('warningObj', \SV\WarningImprovements\Listener::$warnngObj);

                if ($options->sv_force_conversation_email_on_warning)
                {
                    $receiveAdminEmail = $user->Option->receive_admin_email;

                    if (!$options->sv_respect_receive_admin_email_on_warning)
                    {
                        $receiveAdminEmail = true;
                    }

                    if ($options->sv_only_force_warning_email_on_banned && !$user->is_banned)
                    {
                        $receiveAdminEmail = false;
                    }

                    return $receiveAdminEmail;
                }
            }
        }

        return parent::_canUserReceiveNotification($user, $sender);
    }
}