<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Globals;
use XF\Entity\Warning;
use XF\Entity\WarningDefinition;

/**
 * Extends \XF\Service\User\Warn
 */
class Warn extends XFCP_Warn
{
    protected $sendAlert = false;

    public function setFromDefinition(WarningDefinition $definition, $points = null, $expiry = null)
    {
        $this->sendAlert = !empty(Globals::$warningInput['send_warning_alert']);
        $custom_title = !empty(Globals::$warningInput['custom_title']) ? Globals::$warningInput['custom_title'] : null;
        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $definition */
        $return = parent::setFromDefinition($definition, $points, $expiry);

        if ($definition->warning_definition_id === 0)
        {
            $this->warning->hydrateRelation('Definition', $definition);
        }

        // force empty because title is already being set from warning definition entity
        if ($this->warning->warning_definition_id === 0)
        {
            $this->warning->title = '';
        }

        if ($custom_title && ($definition->sv_custom_title || $definition->warning_definition_id === 0))
        {
            $this->warning->title = $custom_title;
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
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->repository('XF:Warning');

        return $warningRepo->getCustomWarningDefinition();
    }

    protected function _save()
    {
        $warning = parent::_save();

        if ($warning instanceof Warning)
        {
            if ($this->sendAlert)
            {
                /** @var \XF\Repository\UserAlert $alertRepo */
                $alertRepo = $this->repository('XF:UserAlert');
                $alertRepo->alertFromUser($warning->User, $warning->WarnedBy, 'warning_alert', $warning->warning_id, 'warning');
            }

            $this->warningActionNotifications();
        }

        return $warning;
    }

    public function warningActionNotifications()
    {
        $options = $this->app->options();

        if ($postSummaryThreadId = $options->sv_post_warning_summary)
        {
            if ($thread = $this->em()->find('XF:Thread', $postSummaryThreadId))
            {
                /** @var \SV\WarningImprovements\XF\Entity\Warning $warning */
                $warning = $this->warning;

                $dateString = date($options->sv_warning_date_format, \XF::$time);

                $warningArray = $warning->toArray();
                $warningArray['username'] = $this->user->username;
                $warningArray['report'] = $warning->Report
                    ? $this->app->router('public')->buildLink('full:reports', $warning->Report)
                    : \XF::phrase('n_a')->render();
                $warningArray['date'] = $dateString;

                $warningUser = \XF::visitor(); //$this->user;
                $threadReplier = \XF::asVisitor($warningUser, function () use ($thread, $warningArray) {
                    /** @var \XF\Service\Thread\Replier $threadReplier */
                    $threadReplier = $this->service('XF:Thread\Replier', $thread);
                    $threadReplier->setIsAutomated();

                    $messageContent = \XF::phrase('Warning_Summary_Message', $warningArray)->render('raw');

                    $threadReplier->setMessage($messageContent);
                    $threadReplier->save();

                    return $threadReplier;
                });

                $threadReplier->sendNotifications();
            }
        }
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
            '{points}'        => $warning->points,
            '{warning_title}' => $warning->title,
            '{warning_link}'  => \XF::app()->router('public')->buildLink('canonical:warnings', $warning),
        ];

        $conversationTitle = strtr(strval($conversationTitle), $replace);
        $conversationMessage = strtr(strval($conversationMessage), $replace);

        $creator->setContent($conversationTitle, $conversationMessage);

        return $creator;
    }

    protected function sendConversation(Warning $warning)
    {
        Globals::$warningObj = $this->warning;
        try
        {
            return parent::sendConversation($warning);
        }
        finally
        {
            Globals::$warningObj = null;
        }
    }
}