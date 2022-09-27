<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;
use SV\WarningImprovements\Entity\SupportsEmbedMetadataTrait;
use XF\Mvc\Entity\Entity;

class ProfilePostComment extends XFCP_ProfilePostComment implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait, SupportsEmbedMetadataTrait;

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