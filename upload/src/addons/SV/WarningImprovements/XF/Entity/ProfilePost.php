<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;
use XF\Mvc\Entity\Entity;

class ProfilePost extends XFCP_ProfilePost implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;

    public function hasDisabledReactionsListForSvWarnImprov(Entity $entity): bool
    {
        if (!$this->hasDisabledReactionsForSvWarnImprov($entity))
        {
            return false;
        }

        if (\XF::visitor()->hasPermission('profilePost', 'bypassSvReactionList'))
        {
            return false;
        }

        return true;
    }
}