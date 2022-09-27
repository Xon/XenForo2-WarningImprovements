<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;
use SV\WarningImprovements\Entity\SupportsEmbedMetadataInterface;
use SV\WarningImprovements\Entity\SupportsEmbedMetadataTrait;
use SV\WarningImprovements\Entity\SupportsWrappingContentWithSpoilerInterface;
use SV\WarningImprovements\Entity\SupportsWrappingContentWithSpoilerTrait;
use XF\Mvc\Entity\Entity;

class ProfilePost extends XFCP_ProfilePost implements SupportsDisablingReactionInterface, SupportsEmbedMetadataInterface, SupportsWrappingContentWithSpoilerInterface
{
    use SupportsDisablingReactionTrait, SupportsEmbedMetadataTrait, SupportsWrappingContentWithSpoilerTrait;

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