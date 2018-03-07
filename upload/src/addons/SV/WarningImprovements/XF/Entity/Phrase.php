<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Globals;

class Phrase extends XFCP_Phrase
{
    protected function _preSave()
    {
        parent::_preSave();

        if ($this->title == 'warning_title.0')
        {
            $this->version_id = Globals::$customWarningPhrase_version_id;
            $this->version_string = Globals::$customWarningPhrase_version_string;
            $this->addon_id = 'SV/WarningImprovements';
        }
    }
}