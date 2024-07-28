<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Service\User;

use SV\WarningImprovements\Entity\SupportsEmbedMetadataInterface;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\Reaction\SupportsDisablingReactionInterface;
use SV\WarningImprovements\XF\Entity\ConversationMaster as ExtendedConversationMasterEntity;
use XF\Entity\User as UserEntity;
use XF\Entity\Warning;
use XF\Entity\WarningDefinition;
use XF\Mvc\Entity\Entity;
use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use XF\Repository\Reaction as ReactionRepo;

/**
 * @Extends \XF\Service\User\Warn
 *
 * @property ExtendedWarningEntity $warning
 */
class Warn extends XFCP_Warn
{
    /** @var bool */
    protected $sendAlert = false;
    /** @var string */
    protected $sendAlertReason = '';
    /**
     * @var \XF\Service\Conversation\Creator
     */
    protected $conversationCreator;

    /**
     * @var \SV\WarningImprovements\XF\Repository\Warning
     */
    protected $warningRepo;

    public function __construct(\XF\App $app, UserEntity $user, $contentType, Entity $content, UserEntity $warningBy)
    {
        parent::__construct($app, $user, $contentType, $content, $warningBy);

        $this->warningRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Warning::class);
    }

    public function setSendAlert(bool $sendAlert, string $sendAlertReason = '')
    {
        $this->sendAlert = $sendAlert;
        $this->sendAlertReason = $sendAlertReason;
    }

    public function setFromDefinition(WarningDefinition $definition, $points = null, $expiry = null)
    {
        $this->setSendAlert(Globals::$warningInput['send_warning_alert'] ?? false, Globals::$warningInput['send_warning_alert_reason'] ?? '');
        $custom_title = !empty(Globals::$warningInput['custom_title']) ? Globals::$warningInput['custom_title'] : null;


        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $definition */
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Warning::class);
        $return = $warningRepo->asVisitorWithLang($this->user, function() use ($definition, $points, $expiry) {
            return parent::setFromDefinition($definition, $points, $expiry);
        });

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
        return $this->warningRepo->getCustomWarningDefinition();
    }

    protected function _save()
    {
        $db = \XF::db();
        $db->beginTransaction();

        $warning = parent::_save();

        if ($this->sendAlert)
        {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Warning::class);
            $warningRepo->sendWarningAlert($warning, 'warning', $this->sendAlertReason);
        }

        $this->warningActionNotifications();

        $db->commit();

        return $warning;
    }

    public function warningActionNotifications()
    {
        $options = $this->app->options();
        $postSummaryForumId = (int)($options->sv_post_warning_summaryForum ?? 0);
        $postSummaryThreadId = (int)($options->sv_post_warning_summary ?? 0);

        if (!$postSummaryForumId && !$postSummaryThreadId)
        {
            return;
        }

        $params = $this->warningRepo->getSvWarningReplaceables(
            $this->user,
            $this->warning,
            null,
            true,
            $this->contentAction, $this->contentActionOptions
        );

        $warningUser = \XF::visitor(); //$this->user;

        if ($postSummaryForumId &&
            ($forum = \SV\StandardLib\Helper::find(\XF\Entity\Forum::class, $postSummaryForumId)))
        {
            /** @var \XF\Entity\Forum|\SV\MultiPrefix\XF\Entity\Forum $forum */
            /** @var \XF\Service\Thread\Creator $threadCreator */
            $threadCreator = $this->warningRepo->asVisitorWithLang($warningUser, function () use ($forum, $params) {
                /** @var \XF\Service\Thread\Creator $threadCreator */
                $threadCreator = \SV\StandardLib\Helper::service(\XF\Service\Thread\Creator::class, $forum);
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
                $this->warningRepo->asVisitorWithLang($warningUser, function () use ($threadCreator) {
                    $threadCreator->sendNotifications();
                });
            });
        }
        else if ($postSummaryThreadId &&
                 ($thread = \SV\StandardLib\Helper::find(\XF\Entity\Thread::class, $postSummaryThreadId)))
        {
            /** @var \XF\Entity\Thread $thread */
            $threadReplier = $this->warningRepo->asVisitorWithLang($warningUser, function () use ($thread, $params) {
                /** @var \XF\Service\Thread\Replier $threadReplier */
                $threadReplier = \SV\StandardLib\Helper::service(\XF\Service\Thread\Replier::class, $thread);
                $threadReplier->setIsAutomated();

                $messageContent = \XF::phrase('Warning_Summary.Message', $params)->render('raw');

                $threadReplier->setMessage($messageContent);
                $threadReplier->save();

                return $threadReplier;
            });

            \XF::runLater(function () use ($threadReplier, $warningUser){
                $this->warningRepo->asVisitorWithLang($warningUser, function () use ($threadReplier) {
                    $threadReplier->sendNotifications();
                });
            });
        }
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
        $user = $this->warningRepo->getWarnedByForUser($warning, true);

        $originalWarningBy = $this->warningBy;
        $this->warningBy = $user;
        $oldWarning = Globals::$warningObj ?? null;
        Globals::$warningObj = $warning;
        try
        {
            return $this->warningRepo->asVisitorWithLang($user, $callback);
        }
        finally
        {
            $this->warningBy = $originalWarningBy;
            Globals::$warningObj = $oldWarning;
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
        return $this->doAsWarningIssuerForSv($warning, function () use ($warning)
        {
            $warnedByUser = \XF::visitor();
            $warnedUser = $warning->User;

            $conversationCreatorSvc = parent::setupConversation($warning);
            // workaround for \XF\Service\Conversation\Pusher::setInitialProperties requiring a user to be set on the Message's User attribute
            $conversationCreatorSvc->getMessage()->hydrateRelation('User', $warnedByUser);

            $replace = $this->warningRepo->getSvWarningReplaceables(
                $warnedUser,
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
        return $this->doAsWarningIssuerForSv($warning, function () use ($warning) {
            return parent::sendConversation($warning);
        });
    }

    public function setContentSpoilerTitleForSvWarnImprove(string $spoilerTitle) : self
    {
        $warning = $this->warning;
        $warning->sv_spoiler_contents = true;
        $warning->sv_content_spoiler_title = $spoilerTitle;

        $content = $this->content;
        if ($content instanceof SupportsEmbedMetadataInterface)
        {
            $embedMetadata = $content->embed_metadata;
            $embedMetadata['sv_spoiler_contents'] = $warning->sv_spoiler_contents;
            $embedMetadata['sv_content_spoiler_title'] = $spoilerTitle;
            $content->embed_metadata = $embedMetadata;
        }

        return $this;
    }

    public function disableReactionsForSvWarnImprov() : self
    {
        $warning = $this->warning;
        $warning->sv_disable_reactions = true;

        /** @var ReactionRepo $reactionRepo */
        $reactionRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Reaction::class);
        $reactionHandler = $reactionRepo->getReactionHandler($warning->content_type);
        if ($reactionHandler instanceof SupportsDisablingReactionInterface)
        {
            $content = $this->content;
            if ($content instanceof SupportsEmbedMetadataInterface)
            {
                $embedMetadata = $content->embed_metadata;
                $embedMetadata['sv_disable_reactions'] = $warning->sv_disable_reactions;

                $content->embed_metadata = $embedMetadata;
            }
        }

        return $this;
    }
}