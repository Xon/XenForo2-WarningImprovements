<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Service\Conversation;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\Warning;
use XF\App;
use XF\Entity\ConversationMaster;
use XF\Entity\User;

class Notifier extends XFCP_Notifier
{
    /** @var Warning */
    protected $warning = null;

    protected $sv_force_email_for_user_id     = null;
    protected $sv_respect_receive_admin_email = true;

    public function __construct(App $app, ConversationMaster $conversation)
    {
        parent::__construct($app, $conversation);

        $this->setWarning(Globals::$warningObj);
    }

    /**
     * @param Warning $warning
     */
    public function setWarning(Warning $warning = null)
    {
        $this->warning = $warning;
    }

    protected function _canUserReceiveNotification(User $user, User $sender = null)
    {
        $canUserReceiveNotification = parent::_canUserReceiveNotification($user, $sender);
        if ($canUserReceiveNotification)
        {
            return $canUserReceiveNotification;
        }

        if ($this->warning)
        {
            if ($this->warning->user_id === $user->user_id)
            {
                if ($this->sv_force_email_for_user_id === null)
                {
                    $this->sv_force_email_for_user_id = 0;
                    $options = $this->app->options();

                    if ($options->sv_force_conversation_email_on_warning)
                    {
                        $this->sv_respect_receive_admin_email = $options->sv_respect_receive_admin_email_on_warning;
                        $this->sv_force_email_for_user_id = $this->warning->user_id;
                    }
                    if ($options->sv_only_force_warning_email_on_banned && !$user->is_banned)
                    {
                        $this->sv_force_email_for_user_id = 0;
                    }
                }

                $email_on_conversation = $user->Option->email_on_conversation;
                $is_banned = $user->is_banned;

                if ($this->sv_force_email_for_user_id)
                {
                    if ($this->sv_respect_receive_admin_email)
                    {
                        $email_on_conversation = $user->Option->receive_admin_email;
                    }
                    else
                    {
                        $email_on_conversation = true;
                    }

                    $is_banned = false;
                }

                $canUserReceiveNotification = (
                    $email_on_conversation
                    && $user->user_state == 'valid'
                    && !$is_banned
                    && (!$sender || $sender->user_id != $user->user_id)
                );
            }
        }

        return $canUserReceiveNotification;
    }
}