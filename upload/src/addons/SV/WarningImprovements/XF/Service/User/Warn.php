<?php

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Globals;
use XF\Entity\Warning;
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
            if (!empty(Globals::$warningInput))
            {
                $this->warning->title = Globals::$warningInput['custom_title'];
            }
        }

        return $return;
    }

    public function setFromCustom($title, $points, $expiry)
    {
        return $this->setFromDefinition($this->getCustomWarningDefinition(), $points, $expiry);
    }

    /**
     * @return \SV\WarningImprovements\XF\Entity\WarningDefinition
     */
    protected function getCustomWarningDefinition()
    {
        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $entity */
        $entity = $this->finder('XF:WarningDefinition')
                       ->where('warning_definition_id', '=', 0)
                       ->fetchOne();

        return $entity;
    }

    protected function _save()
    {
        $warning = parent::_save();

        if ($warning instanceof Warning)
        {
            if (!empty(Globals::$warningInput['send_warning_alert']))
            {
                /** @var \XF\Repository\UserAlert $alertRepo */
                $alertRepo = $this->repository('XF:UserAlert');
                $alertRepo->alertFromUser($warning->User, $warning->WarnedBy, 'warning_alert', $warning->warning_id, 'warning');
            }
        }

        return $warning;
    }

    protected function _validate()
    {
        $errors = parent::_validate();

        if (!$this->warning->canView($error))
        {
            $errors[] = $error;
        }

        return $errors;
    }

    protected function setupConversation(Warning $warning)
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