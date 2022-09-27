<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Entity\SupportsDisablingReactionTrait;
use XF\Mvc\Entity\Entity;

class Post extends XFCP_Post implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;

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