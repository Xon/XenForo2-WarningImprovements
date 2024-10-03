<?php
/**
 * @noinspection PhpUnusedParameterInspection
 */

namespace SV\WarningImprovements\XF\Service\Conversation;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use XF\App;
use XF\Entity\ConversationMaster;
use XF\Entity\User as UserEntity;

/**
 * @extends \XF\Service\Conversation\Notifier
 */
class Notifier extends XFCP_Notifier
{
    /** @var ExtendedWarningEntity */
    protected $warning = null;

    protected $sv_force_email_for_user_id     = null;
    protected $sv_respect_receive_admin_email = true;

    public function __construct(App $app, ConversationMaster $conversation)
    {
        parent::__construct($app, $conversation);

        $this->setWarning(Globals::$warningObj ?? null);
    }

    public function setWarning(?ExtendedWarningEntity $warning = null)
    {
        $this->warning = $warning;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function _canUserReceiveNotification(UserEntity $user, ?UserEntity $sender = null)
    {
        $canUserReceiveNotification = parent::_canUserReceiveNotification($user, $sender);
        if ($canUserReceiveNotification)
        {
            return true;
        }

        if ($this->warning && $user->email)
        {
            if ($this->warning->user_id === $user->user_id)
            {
                if ($this->sv_force_email_for_user_id === null)
                {
                    $this->sv_force_email_for_user_id = 0;
                    $options = \XF::app()->options();

                    if ($options->sv_force_conversation_email_on_warning ?? true)
                    {
                        $this->sv_respect_receive_admin_email = $options->sv_respect_receive_admin_email_on_warning ?? false;
                        $this->sv_force_email_for_user_id = $this->warning->user_id;
                    }
                    if (($options->sv_only_force_warning_email_on_banned ?? true) && !$user->is_banned)
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
                    && $user->user_state === 'valid'
                    && !$is_banned
                    && (!$sender || $sender->user_id !== $user->user_id)
                );
            }
        }

        return $canUserReceiveNotification;
    }

    /**
     * @since @since 2.5.6
     *
     * @param UserEntity $user
     * @param UserEntity|null $sender
     *
     * @return bool
     */
    protected function isForcingEmailNotificationForUser(UserEntity $user, ?UserEntity $sender = null) : bool
    {
        if (!$user->user_id)
        {
            return false;
        }

        return $this->sv_force_email_for_user_id === $user->user_id;
    }

    /**
     * @since 2.5.6
     *
     * @param UserEntity $user
     * @param UserEntity|null $sender
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function _canUserReceiveEmailNotification(UserEntity $user, ?UserEntity $sender = null)
    {
        return parent::_canUserReceiveEmailNotification($user, $sender)
            || $this->isForcingEmailNotificationForUser($user, $sender);
    }
}