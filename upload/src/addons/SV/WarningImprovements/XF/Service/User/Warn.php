<?php

namespace SV\WarningImprovements\XF\Service\User;
use XF\Entity\WarningDefinition;

/**
 * Extends \XF\Service\User\Warn
 */
class Warn extends XFCP_Warn
{
    public function setFromDefinition(WarningDefinition $definition, $points = null, $expiry = null)
    {
        if ($definition->warning_definition_id !== 0)
        {
            $customWarningDefinition = $this->getCustomWarningDefinition();

            if ($points === null)
            {
                $points = $customWarningDefinition->points_default;
            }

            if ($expiry === null)
            {
                if ($customWarningDefinition->expiry_type == 'never')
                {
                    $expiry = 0;
                }
                else
                {
                    $expiry = strtotime('+' . $customWarningDefinition->expiry_default . ' ' . $customWarningDefinition->expiry_type);
                }
            }

            // if the expiry is too far in the future, just make it actually be permanent
            if ($expiry >= pow(2, 32) - 1)
            {
                $expiry = 0;
            }
        }

        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $definition */
        $return = parent::setFromDefinition($definition, $points, $expiry);

        if ($definition->warning_definition_id === 0)
        {
            $this->warning->hydrateRelation('Definition', $definition);
        }

        if ($definition->sv_custom_title || $definition->warning_definition_id === 0)
        {
            if (!empty(\SV\WarningImprovements\Listener::$warningInput))
            {
                $this->warning->title = \SV\WarningImprovements\Listener::$warningInput['custom_title'];
            }
        }

        return $return;
    }

    public function setFromCustom($title, $points, $expiry)
    {
        return $this->setFromDefinition($this->getCustomWarningDefinition(), $points, $expiry);
    }

    /**
     * @return \SV\WarningImprovements\XF\Entity\WarningDefinition|\XF\Entity\WarningDefinition
     */
    protected function getCustomWarningDefinition()
    {
        return $this->finder('XF:WarningDefinition')
            ->where('warning_definition_id', '=', 0)
            ->fetchOne();
    }

    protected function setupConversation(\XF\Entity\Warning $warning)
    {
        /** @var \XF\Service\Conversation\Creator $creator */
        $creator = parent::setupConversation($warning);

        $conversationTitle = $this->conversationTitle;
        $conversationMessage = $this->conversationMessage;

        $replace = [
            '{points}' => $warning->points,
            '{warning_title}' => $warning->title,
            '{warning_link}' => \XF::app()->router('public')->buildLink('canonical:warnings', $warning),
        ];

        $conversationTitle = strtr(strval($conversationTitle), $replace);
        $conversationMessage = strtr(strval($conversationMessage), $replace);

        $creator->setContent($conversationTitle, $conversationMessage);

        return $creator;
    }
}