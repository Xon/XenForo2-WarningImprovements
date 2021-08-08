<?php

namespace SV\WarningImprovements\Service\Warning;

use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class Editor extends AbstractService
{
    use ValidateAndSavableTrait;

    /** @var ExtendedWarningEntity */
    protected $warning;
    protected $hasChanges = false;

    /** @var string */
    protected $contentAction = '';
    /**  @var array */
    private $contentActionOptions = [];

    public function __construct(\XF\App $app, ExtendedWarningEntity $warning)
    {
        parent::__construct($app);
        $this->warning = $warning;
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
                break;
            case 'future':
                $expiryDate = strtotime("+$expiryLength $expiryUnit");
                if ($expiryDate >= pow(2, 32) - 1)
                {
                    $expiryDate = 0;
                }
                $this->warning->expiry_date = $expiryDate;
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
        // Warning::_preSave() blocks changing points :(
        $this->warning->points = $points;
    }

    protected function detectContactActionChanges(string $contentAction, array $contentActionOptions): bool
    {
        $content = $this->warning->Content;

        switch($contentAction)
        {
            case 'public':
                if ($content->isValidColumn('warning_message') || $content->isValidGetter('warning_message'))
                {
                    $publicWarning = $content->get('warning_message');
                    if ($publicWarning === ($contentActionOptions['message'] ?? ''))
                    {
                        return false;
                    }
                }
                break;
            case 'delete':
                if (!$content->hasRelation('DeletionLog'))
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
        $validActions = $handler->getAvailableContentActions($content);
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
            $warning->sv_acknowledgement = ($warningInput['sv_acknowledgement'] ?? false) ? 'pending' : 'not_required';
            $warning->sv_suppress_notices = $warningInput['sv_suppress_notices'] ?? false;
        }

        $warning->sv_user_note = $warningInput['sv_user_note'] ?? '';
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
            $warning->resolveReportFor($resolveReport, $alert, $alertComment);
            $this->hasChanges = true;
        }
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

    protected function _save(): ExtendedWarningEntity
    {
        // avoid generating unneeded chatter in logs
        if (!$this->hasChanges())
        {
            return $this->warning;
        }

        $db = $this->db();
        $db->beginTransaction();

        $this->warning->clearCache('sv_warning_actions');
        $this->warning->save(true, false);

        $this->postSave();

        $db->commit();

        return $this->warning;
    }

    protected function postSave()
    {
        if (\strlen($this->contentAction))
        {
            $handler = $this->warning->getHandler();
            $content = $this->warning->Content;
            if ($content !== null)
            {
                $handler->takeContentAction($content, $this->contentAction, $this->contentActionOptions);
            }
        }
    }
}