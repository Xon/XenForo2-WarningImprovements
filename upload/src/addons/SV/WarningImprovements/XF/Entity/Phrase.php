<?php

namespace SV\WarningImprovements\XF\Entity;

class Phrase extends XFCP_Phrase
{
    protected function _preSave()
    {
        parent::_preSave();

        if ($this->title == 'warning_title.0')
        {
            $this->version_id = \SV\WarningImprovements\Listener::$customWarningPhrase_version_id;
            $this->version_string = \SV\WarningImprovements\Listener::$customWarningPhrase_version_string;
            $this->addon_id = 'SV/WarningImprovements';
        }
    }
}