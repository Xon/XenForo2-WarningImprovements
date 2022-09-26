<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;

class Post extends XFCP_Post implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;

    public function canReact(&$error = null)
    {
        return parent::canReact($error)
            && !$this->hasDisabledReactionsForSvWarnImprov($this, $error);
    }

    public function getReactions()
    {
        if ($this->hasDisabledReactionsForSvWarnImprov($this))
        {
            return [];
        }

        return parent::getReactions();
    }
}