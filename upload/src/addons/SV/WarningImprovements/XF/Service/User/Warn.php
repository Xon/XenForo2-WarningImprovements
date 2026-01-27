<?php

namespace SV\WarningImprovements\XF\Service\User;

use SV\MultiPrefix\XF\Entity\Forum as MultiPrefixForumEntity;
use SV\StandardLib\Helper;
use SV\WarningImprovements\Entity\SupportsEmbedMetadataInterface;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\Reaction\SupportsDisablingReactionInterface;
use SV\WarningImprovements\XF\Entity\ConversationMaster as ExtendedConversationMasterEntity;
use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\App;
use XF\Entity\ConversationMaster as ConversationMasterEntity;
use XF\Entity\Forum as ForumEntity;
use XF\Entity\Thread as ThreadEntity;
use XF\Entity\User as UserEntity;
use XF\Entity\Warning as WarningEntity;
use XF\Entity\WarningDefinition as WarningDefinitionEntity;
use XF\Mvc\Entity\Entity;
use XF\Repository\Reaction as ReactionRepo;
use XF\Repository\Warning as WarningRepo;
use XF\Service\Conversation\Creator as ConversationCreatorService;
use XF\Service\Thread\Creator as ThreadCreatorService;
use XF\Service\Thread\Replier as ThreadReplierService;
use XF\Service\User\Warn as WarningService;
use function strtr;
use function strval;

/**
 * @extends WarningService
 * @property ExtendedWarningEntity $warning
 */
class Warn extends XFCP_Warn
{
    /** @var bool */
    protected $sendAlert = false;
    /** @var string */
    protected $sendAlertReason = '';
    /**
     * @var ConversationCreatorService
     */
    protected $conversationCreator;

    /**
     * @var ExtendedWarningRepo
     */
    protected $warningRepo;

    public function __construct(App $app, UserEntity $user, $contentType, Entity $content, UserEntity $warningBy)
    {
        parent::__construct($app, $user, $contentType, $content, $warningBy);

        $this->warningRepo = Helper::repository(WarningRepo::class);
    }

    public function setSendAlert(bool $sendAlert, string $sendAlertReason = '')
    {
        $this->sendAlert = $sendAlert;
        $this->sendAlertReason = $sendAlertReason;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function setFromDefinition(WarningDefinitionEntity $definition, $points = null, $expiry = null)
    {
        $this->setSendAlert(Globals::$warningInput['send_warning_alert'] ?? false, Globals::$warningInput['send_warning_alert_reason'] ?? '');
        $custom_title = !empty(Globals::$warningInput['custom_title']) ? Globals::$warningInput['custom_title'] : null;


        /** @var ExtendedWarningDefinitionEntity $definition */
        $return = Globals::asVisitorWithLang($this->user, function () use ($definition, $points, $expiry): WarningService {
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

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function setFromCustom($title, $points, $expiry)
    {
        Globals::$warningInput['custom_title'] = $title;

        return $this->setFromDefinition($this->getCustomWarningDefinition(), $points, $expiry);
    }

    protected function getCustomWarningDefinition(): ExtendedWarningDefinitionEntity
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
            $this->warningRepo->sendWarningAlert($warning, 'warning', $this->sendAlertReason);
        }

        $this->warningActionNotifications();

        $db->commit();

        return $warning;
    }

    public function warningActionNotifications()
    {
        $options = \XF::app()->options();
        $postSummaryForumId = $options->sv_post_warning_summaryForum ?? 0;
        $postSummaryThreadId = $options->sv_post_warning_summary ?? 0;

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
            ($forum = Helper::find(ForumEntity::class, $postSummaryForumId)))
        {
            /** @var ForumEntity|MultiPrefixForumEntity $forum */
            $threadCreator = Globals::asVisitorWithLang($warningUser, function () use ($forum, $params): ThreadCreatorService {
                $threadCreator = Helper::service(ThreadCreatorService::class, $forum);
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

            \XF::runLater(function () use ($threadCreator, $warningUser) {
                Globals::asVisitorWithLang($warningUser, function () use ($threadCreator): void {
                    $threadCreator->sendNotifications();
                });
            });
        }
        else if ($postSummaryThreadId &&
                 ($thread = Helper::find(ThreadEntity::class, $postSummaryThreadId)))
        {
            $threadReplier = Globals::asVisitorWithLang($warningUser, function () use ($thread, $params): ThreadReplierService {
                $threadReplier = Helper::service(ThreadReplierService::class, $thread);
                $threadReplier->setIsAutomated();

                $messageContent = \XF::phrase('Warning_Summary.Message', $params)->render('raw');

                $threadReplier->setMessage($messageContent);
                $threadReplier->save();

                return $threadReplier;
            });

            \XF::runLater(function () use ($threadReplier, $warningUser) {
                Globals::asVisitorWithLang($warningUser, function () use ($threadReplier): void {
                    $threadReplier->sendNotifications();
                });
            });
        }
    }

    /**
     * @param WarningEntity $warning
     * @param callable(): T $callback
     * @return T
     * @throws \Exception
     * @since 2.5.7
     * @template T
     */
    protected function doAsWarningIssuerForSv(WarningEntity $warning, callable $callback)
    {
        $user = $this->warningRepo->getWarnedByForUser($warning, true);

        $originalWarningBy = $this->warningBy;
        $this->warningBy = $user;
        $oldWarning = Globals::$warningObj ?? null;
        Globals::$warningObj = $warning;
        try
        {
            return Globals::asVisitorWithLang($user, $callback);
        }
        finally
        {
            $this->warningBy = $originalWarningBy;
            Globals::$warningObj = $oldWarning;
        }
    }

    /**
     * @param WarningEntity $warning
     * @return ConversationCreatorService
     * @throws \Exception
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpMissingReturnTypeInspection
     * @since        2.5.7
     */
    protected function setupConversation(WarningEntity $warning)
    {
        return $this->doAsWarningIssuerForSv($warning, function () use ($warning): ConversationCreatorService {
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
                strtr(strval($this->conversationTitle), $replace),
                strtr(strval($this->conversationMessage), $replace)
            );

            return $conversationCreatorSvc;
        });
    }

    /**
     * @param WarningEntity $warning
     * @return Entity|ExtendedConversationMasterEntity|null
     * @throws \Exception
     * @noinspection PhpMissingParentCallCommonInspection
     * @since        2.5.7
     */
    protected function sendConversation(WarningEntity $warning)
    {
        return $this->doAsWarningIssuerForSv($warning, function () use ($warning): ?ConversationMasterEntity {
            return parent::sendConversation($warning);
        });
    }

    public function setContentSpoilerTitleForSvWarnImprove(string $spoilerTitle): self
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

    public function disableReactionsForSvWarnImprov(): self
    {
        $warning = $this->warning;
        $warning->sv_disable_reactions = true;

        /** @var ReactionRepo $reactionRepo */
        $reactionRepo = Helper::repository(ReactionRepo::class);
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