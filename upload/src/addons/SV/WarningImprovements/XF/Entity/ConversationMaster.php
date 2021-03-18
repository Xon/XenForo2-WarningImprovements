<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\ConversationMaster
 */
class ConversationMaster extends XFCP_ConversationMaster
{
    public function getIsConversationForWarning(): bool
    {
        return !empty($this->getOption('warningObj'));
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['is_conversation_for_warning'] = true;

        $structure->options['warningObj'] = null;

        return $structure;
    }
}