<?php

namespace SV\WarningImprovements\XF\Repository;

use XF\Entity\ConversationMaster as ConversationMasterEntity;
use XF\Entity\User as UserEntity;

/**
 * @extends \XF\Repository\Conversation
 */
class Conversation extends XFCP_Conversation
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function insertRecipients(ConversationMasterEntity $conversation, array $recipientUsers, ?UserEntity $from = null)
    {
        if ($from && !$from->user_id)
        {
            $from = null;
            /** @var UserEntity[] $recipientUsers */
            if (!$recipientUsers[0]->user_id)
            {
                unset($recipientUsers[0]);
            }
        }

        return parent::insertRecipients($conversation, $recipientUsers, $from);
    }
}