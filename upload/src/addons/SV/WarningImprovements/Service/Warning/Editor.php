<?php

namespace SV\WarningImprovements\Service\Warning;

use SV\WarningImprovements\Entity\SupportsEmbedMetadataInterface;
use SV\WarningImprovements\Reaction\SupportsDisablingReactionInterface;
use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use XF\Mvc\Entity\Entity;
use XF\Repository\Reaction as ReactionRepo;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use function array_key_exists;

class Editor extends AbstractService
{
    use ValidateAndSavableTrait;

    /** @var ExtendedWarningEntity */
    protected $warning;
    /** @var bool */
    protected $hasChanges = false;

    /** @var string|null */
    protected $publicBanner = null;
    /** @var int|null */
    protected $points = null;

    /** @var string */
    protected $contentAction = '';
    /**  @var array */
    protected $contentActionOptions = [];
    /** @var bool */
    protected $sendAlert = false;
    /** @var string */
    protected $sendAlertReason = '';

    /**
     * @var null|Entity
     */
    protected $content = null;

    public function __construct(\XF\App $app, ExtendedWarningEntity $warning)
    {
        $this->warning = $warning;
        parent::__construct($app);
    }

    public function setup()
    {
        $content = $this->warning->Content;
        if ($content === null)
        {
            return;
        }

        if ($content->isValidColumn('warning_message') || $content->isValidGetter('warning_message'))
        {
            $this->publicBanner = (string)$content->get('warning_message');
        }

        $this->content = $this->warning->getContent();
    }

    public function getWarning(): ExtendedWarningEntity
    {
        return $this->warning;
    }

    public function setSendAlert(bool $sendAlert, string $reason = '')
    {
        $this->sendAlert = $sendAlert;
        $this->sendAlertReason = $reason;
    }

    public function setTitle(string $title)
    {
        $this->warning->title = $title;
    }

    public function setExpiry(string $expiry, int $expiryLength, string $expiryUnit)
    {
        switch($expiry)
        {
            case 'now':
                $this->warning->expiry_date = \XF::$time;
                $this->warning->is_expired = true;
                break;
            case 'future':
                $expiryDate = @\strtotime("+$expiryLength $expiryUnit");
                if ($expiryDate === false)
                {
                    $this->warning->error(\XF::phrase('svWarningImprovements_invalid_offset_date', [
                        'expiryLength' => $expiryLength,
                        'expiryUnit'   => $expiryUnit,
                    ]), 'expiry_date');
                }
                $expiryDate = (int)$expiryDate;
                if ($expiryDate >= pow(2, 32) - 1)
                {
                    $expiryDate = 0;
                }
                $this->warning->expiry_date = $expiryDate;
                $this->warning->is_expired = ($expiryDate <= \XF::$time);
                break;
            case 'no_change':
            default:
                break;
        }
    }

    public function setNotes(string $title)
    {
        $this->warning->notes = $title;
    }

    public function setPoints(int $points)
    {
        $this->points = $points;
        // Warning::_preSave() blocks changing points :(
        //$this->warning->points = $points;
    }

    protected function detectContactActionChanges(string $contentAction, array $contentActionOptions): bool
    {
        $content = $this->warning->Content;

        switch($contentAction)
        {
            case 'public':
                if (($contentActionOptions['message'] ?? '') === $this->publicBanner)
                {
                    return false;
                }
                break;
            case 'delete':
                if (!array_key_exists('DeletionLog', $content->structure()->relations))
                {
                    return false;
                }

                /** @var \XF\Entity\DeletionLog $deletionLog */
                $deletionLog = $content->getRelation('DeletionLog');
                if ($deletionLog !== null)
                {
                    if ($deletionLog->delete_reason === ($contentActionOptions['reason'] ?? ''))
                    {
                        return false;
                    }
                }
                break;
        }

        return true;
    }

    public function setContentActions(string $contentAction, array $contentActionOptions)
    {
        $content = $this->warning->Content;
        if ($content === null)
        {
            return;
        }
        $contentActionOptions = $contentActionOptions[$contentAction] ?? [];

        $handler = $this->warning->getHandler();
        $validActions = $handler ? $handler->getAvailableContentActions($content) : [];
        if (($validActions[$contentAction] ?? false) && $this->detectContactActionChanges($contentAction, $contentActionOptions))
        {
            $this->contentAction = $contentAction;
            $this->contentActionOptions = $contentActionOptions;
            $this->hasChanges = true;
        }
    }

    public function setWarningAck(array $warningInput)
    {
        /** @var \SV\WarningAcknowledgement\XF\Entity\Warning $warning */
        $warning = $this->warning;
        if ($warning->sv_acknowledgement !== 'completed')
        {
            $warning->sv_acknowledgement = $warningInput['sv_acknowledgement'] ?? 'not_required';
            $warning->sv_suppress_notices = $warningInput['sv_suppress_notices'] ?? false;
        }

        $warning->sv_user_note = $warningInput['sv_user_note'] ?? '';
    }

    public function setCanReopenReport(bool $canReopenReport)
    {
        $this->warning->setOption('svCanReopenReport', $canReopenReport);
    }

    public function resolveReportFor(bool $resolveReport, bool $alert, string $alertComment)
    {
        if (!$resolveReport)
        {
            return;
        }

        /** @var \SV\ReportImprovements\XF\Entity\Warning $warning */
        $warning = $this->warning;
        $report = $warning->Report;
        if ($report === null || !$report->isClosed())
        {
            $warning->resolveReportFor(true, $alert, $alertComment);
            $this->hasChanges = true;
        }
    }

    public function setSpoilerContents(
        bool $spoilerContents,
        string $spoilerTitle,
        bool $forceSpoilerTitleUpdate = false) : self
    {
        $warning = $this->warning;
        $warning->sv_spoiler_contents = $spoilerContents;

        // If the spoiler contents checkbox is not checked, the spoiler title field will get unset
        if ($spoilerContents || $forceSpoilerTitleUpdate)
        {
            $warning->sv_content_spoiler_title = $spoilerTitle;
        }

        return $this;
    }

    public function setDisableReactions(bool $disableReactions) : self
    {
        $warning = $this->warning;
        $warning->sv_disable_reactions = $disableReactions;

        return $this;
    }

    protected function _validate(): array
    {
        $this->finalSetup();

        $this->warning->preSave();
        return $this->warning->getErrors();
    }

    protected function finalSetup()
    {
    }

    protected function hasChanges(): bool
    {
        return $this->hasChanges || $this->warning->hasChanges();
    }

    /**
     * @return ExtendedWarningEntity|null
     * @throws \XF\PrintableException
     */
    protected function _save()
    {
        $db = \XF::db();
        $db->beginTransaction();

        $this->preUpdate();

        // avoid generating unneeded chatter in logs
        if (!$this->hasChanges())
        {
            $db->rollback();

            return null;
        }

        $this->warning->save(true, false);

        $this->postUpdate();

        $db->commit();

        return $this->warning;
    }

    protected function preUpdate()
    {
        $this->warning->clearCache('sv_warning_actions');

        if ($this->warning->hasOption('svPublicBanner'))
        {
            $this->warning->setOption('svPublicBanner', $this->publicBanner);
        }

        if ($this->points !== null)
        {
            $this->warning->set('points', $this->points, [
                'forceSet' => true,
            ]);
        }

        if (\strlen($this->contentAction) !== 0)
        {
            $this->applyContentAction();
        }
    }

    protected function postUpdate()
    {
        if ($this->sendAlert)
        {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Warning::class);
            $warningRepo->sendWarningAlert($this->warning, 'edit', $this->sendAlertReason);
        }

        $content = $this->content;
        if ($content instanceof SupportsEmbedMetadataInterface)
        {
            $warning = $this->warning;
            $embedMetadata = $content->embed_metadata;

            if ($warning->sv_spoiler_contents)
            {
                $embedMetadata['sv_spoiler_contents'] = $warning->sv_spoiler_contents;
                $embedMetadata['sv_content_spoiler_title'] = $warning->sv_content_spoiler_title ?? '';
            }
            else
            {
                unset($embedMetadata['sv_spoiler_contents']);
                unset($embedMetadata['sv_content_spoiler_title']);
            }

            /** @var ReactionRepo $reactionRepo */
            $reactionRepo = \SV\StandardLib\Helper::repository(\XF\Repository\Reaction::class);
            $reactionHandler = $reactionRepo->getReactionHandler($warning->content_type);
            if ($reactionHandler instanceof SupportsDisablingReactionInterface)
            {
                if ($warning->sv_disable_reactions)
                {
                    $embedMetadata['sv_disable_reactions'] = $warning->sv_disable_reactions;
                }
                else
                {
                    unset($embedMetadata['sv_disable_reactions']);
                }
            }

            $content->fastUpdate('embed_metadata', $embedMetadata);
        }
    }

    public function applyContentAction()
    {
        $warning = $this->warning;
        $handler = $warning->getHandler();
        $content = $warning->Content;
        if ($content === null || $handler == null)
        {
            return;
        }

        // dumb way to reliably remove the public banner...
        if ($this->contentAction === 'public')
        {
            $message = $this->contentActionOptions['message'] ?? '';
            if (\strlen($message) === 0)
            {
                $handler->onWarningRemoval($content, $this->warning);
                $handler->onWarning($content, $this->warning);
            }

            if ($this->warning->hasOption('svPublicBanner'))
            {
                $this->warning->setOption('svPublicBanner', $message);
            }
        }

        $handler->takeContentAction($content, $this->contentAction, $this->contentActionOptions);
    }
}