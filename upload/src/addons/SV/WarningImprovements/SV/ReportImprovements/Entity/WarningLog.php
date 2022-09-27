<?php

namespace SV\WarningImprovements\SV\ReportImprovements\Entity;

use XF\Mvc\Entity\Structure as EntityStructure;

class WarningLog extends XFCP_WarningLog
{
    public static function getStructure(EntityStructure $structure) : EntityStructure
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_spoiler_contents'] = [
            'type' => self::BOOL,
            'default' => false
        ];
        $structure->columns['sv_content_spoiler_title'] = [
            'type' => self::STR,
            'default' => ''
        ];
        $structure->columns['sv_disable_reactions'] = [
            'type' => self::BOOL,
            'default' => false
        ];

        return $structure;
    }
}