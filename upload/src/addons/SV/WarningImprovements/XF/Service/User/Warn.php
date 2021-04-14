<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\ConversationMaster as ExtendedConversationMasterEntity;
use XF\Entity\Warning;
use XF\Entity\WarningDefinition;
use XF\Mvc\Entity\Entity;

/**
 * Extends \XF\Service\User\Warn
 */
class Warn extends XFCP_Warn
{
    protected $sendAlert = false;
    /**
     * @var \XF\Service\Conversation\Creator
     */
    protected $conversationCreator;

    public function setSendAlert(bool $sendAlert)
    {
        $this->sendAlert = $sendAlert;
    }

    public function setFromDefinition(WarningDefinition $definition, $points = null, $expiry = null)
    {
        $this->setSendAlert(!empty(Globals::$warningInput['send_warning_alert']));
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
        Globals::$warningInput['custom_title'] = $title;
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

    /**
     * @param bool $conversation
     * @return \SV\WarningImprovements\XF\Entity\User|\XF\Entity\User|\XF\Mvc\Entity\Entity
     */
    protected function getWarnedByForUser(bool $conversation)
    {
        /** @var \SV\WarningImprovements\XF\Entity\Warning $warning */
        $warning = $this->warning;

        if ($conversation && (\XF::options()->svWarningImprovAnonymizeConversations ?? false))
        {
            return $warning->WarnedBy;
        }

        if ($warning->User->canViewIssuer()) // the user getting warned
        {
            return $warning->WarnedBy;
        }

        return $warning->getAnonymizedIssuer();
    }

    protected function _save()
    {
        $db = \XF::db();
        $db->beginTransaction();

        $warning = parent::_save();

        if ($this->sendAlert)
        {
            $warnedBy = $this->getWarnedByForUser(false);
            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = $this->repository('XF:UserAlert');
            $alertRepo->alertFromUser($warning->User, $warnedBy, 'warning_alert', $warning->warning_id, 'warning');
        }

        $this->warningActionNotifications();

        $db->commit();

        return $warning;
    }

    public function warningActionNotifications()
    {
        $options = $this->app->options();
        $postSummaryForumId = $options->sv_post_warning_summaryForum;
        $postSummaryThreadId = $options->sv_post_warning_summary;

        if (!$postSummaryForumId && !$postSummaryThreadId)
        {
            return;
        }

        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = \XF::repository('XF:Warning');
        $params = $warningRepo->getSvWarningReplaceables(
            $this->user,
            $this->warning,
            null,
            true,
            $this->contentAction, $this->contentActionOptions
        );

        $warningUser = \XF::visitor(); //$this->user;

        if ($postSummaryForumId &&
            ($forum = $this->em()->find('XF:Forum', $postSummaryForumId)))
        {
            /** @var \XF\Entity\Forum|\SV\MultiPrefix\XF\Entity\Forum $forum */
            /** @var \XF\Service\Thread\Creator $threadCreator */
            $threadCreator = \XF::asVisitor($warningUser, function () use ($forum, $params) {
                /** @var \XF\Service\Thread\Creator $threadCreator */
                $threadCreator = $this->service('XF:Thread\Creator', $forum);
                $threadCreator->setIsAutomated();

                $defaultPrefix = $forum->sv_default_prefix_ids ?? $forum->default_prefix_id;
                if ($defaultPrefix)
                {
                    $threadCreator->setPrefix($defaultPrefix);
                }

                $title = \XF::phrase('Warning_Summary.Title', $params)->render('raw');
                $messageContent = \XF::phrase('Warning_Summary.Message', $params)->render('raw');

                $threadCreator->setContent($title, $messageContent);
                $threadCreator->save();

                return $threadCreator;
            });

            \XF::runLater(function () use ($threadCreator, $warningUser){
                \XF::asVisitor($warningUser, function () use ($threadCreator) {
                    $threadCreator->sendNotifications();
                });
            });
        }
        else if ($postSummaryThreadId &&
                 ($thread = $this->em()->find('XF:Thread', $postSummaryThreadId)))
        {
            /** @var \XF\Entity\Thread $thread */
            $threadReplier = \XF::asVisitor($warningUser, function () use ($thread, $params) {
                /** @var \XF\Service\Thread\Replier $threadReplier */
                $threadReplier = $this->service('XF:Thread\Replier', $thread);
                $threadReplier->setIsAutomated();

                $messageContent = \XF::phrase('Warning_Summary.Message', $params)->render('raw');

                $threadReplier->setMessage($messageContent);
                $threadReplier->save();

                return $threadReplier;
            });

            \XF::runLater(function () use ($threadReplier, $warningUser){
                \XF::asVisitor($warningUser, function () use ($threadReplier) {
                    $threadReplier->sendNotifications();
                });
            });
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

    /**
     * @since 2.5.7
     *
     * @param Warning $warning
     * @param callable $callback
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function doAsWarningIssuerForSv(Warning $warning, callable $callback)
    {
        $user = $this->getWarnedByForUser(true);

        Globals::$warningObj = $warning;
        try
        {
            return \XF::asVisitor($user, $callback);
        }
        finally
        {
            Globals::$warningObj = null;
        }
    }

    /**
     * @since 2.5.7
     *
     * @param Warning $warning
     *
     * @return \XF\Service\Conversation\Creator
     *
     * @throws \Exception
     */
    protected function setupConversation(Warning $warning)
    {
        return $this->doAsWarningIssuerForSv($warning, function () use($warning)
        {
            $user = \XF::visitor();

            $originalWarningBy = $this->warningBy;
            $this->warningBy = $user;
            try
            {
                $conversationCreatorSvc = parent::setupConversation($warning);
            }
            finally
            {
                $this->warningBy = $originalWarningBy;
            }
            // workaround for \XF\Service\Conversation\Pusher::setInitialProperties requiring a user to be set on the Message's User attribute
            $conversationCreatorSvc->getMessage()->hydrateRelation('User', $user);

            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = \XF::repository('XF:Warning');
            $replace = $warningRepo->getSvWarningReplaceables(
                $user,
                $warning,
                null,
                false,
                $this->contentAction, $this->contentActionOptions
            );

            $conversationCreatorSvc->setContent(
                \strtr(\strval($this->conversationTitle), $replace),
                \strtr(\strval($this->conversationMessage), $replace)
            );

            return $conversationCreatorSvc;
        });
    }

    /**
     * @since 2.5.7
     *
     * @param Warning $warning
     *
     * @return Entity|ExtendedConversationMasterEntity|null
     *
     * @throws \Exception
     */
    protected function sendConversation(Warning $warning)
    {
        return $this->doAsWarningIssuerForSv($warning, function () use($warning)
        {
            return parent::sendConversation($warning);
        });
    }
}