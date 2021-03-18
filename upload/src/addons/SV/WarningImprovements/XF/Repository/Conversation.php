<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Repository;

/**
 * Extends \XF\Repository\Conversation
 */
class Conversation extends XFCP_Conversation
{
    public function insertRecipients(\XF\Entity\ConversationMaster $conversation, array $recipientUsers, \XF\Entity\User $from = null)
    {
        if ($from && !$from->user_id)
        {
            $from = null;
            /** @var \XF\Entity\User[] $recipientUsers */
            if (!$recipientUsers[0]->user_id)
            {
                unset($recipientUsers[0]);
            }
        }

        return parent::insertRecipients($conversation, $recipientUsers, $from);
    }
}