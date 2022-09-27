<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;
use SV\WarningImprovements\Entity\SupportsEmbedMetadataInterface;
use SV\WarningImprovements\Entity\SupportsEmbedMetadataTrait;
use SV\WarningImprovements\Entity\SupportsWrappingContentWithSpoilerInterface;
use SV\WarningImprovements\Entity\SupportsWrappingContentWithSpoilerTrait;
use XF\Mvc\Entity\Entity;

class Post extends XFCP_Post implements SupportsDisablingReactionInterface, SupportsEmbedMetadataInterface, SupportsWrappingContentWithSpoilerInterface
{
    use SupportsDisablingReactionTrait, SupportsEmbedMetadataTrait, SupportsWrappingContentWithSpoilerTrait;

    public function hasDisabledReactionsListForSvWarnImprov(Entity $entity): bool
    {
        if (!$this->hasDisabledReactionsForSvWarnImprov($entity))
        {
            return false;
        }

        $thread = $this->Thread;
        if (!$thread)
        {
            return true;
        }

        if (\XF::visitor()->hasNodePermission($thread->node_id, 'bypassSvReactionList'))
        {
            return false;
        }

        return true;
    }
}