<?php

namespace SV\WarningImprovements\SV\ReportImprovements\Service\WarningLog;

/**
 * @extends \SV\ReportImprovements\Service\WarningLog\Creator
 */
class Creator extends XFCP_Creator
{
    protected function getFieldsToLog(): array
    {
        $fieldsToLog = parent::getFieldsToLog();

        $fieldsToLog[] = 'sv_spoiler_contents';
        $fieldsToLog[] = 'sv_content_spoiler_title';
        $fieldsToLog[] = 'sv_disable_reactions';

        return $fieldsToLog;
    }
}