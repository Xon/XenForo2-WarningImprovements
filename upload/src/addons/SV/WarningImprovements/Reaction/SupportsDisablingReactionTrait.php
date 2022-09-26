<?php

namespace SV\WarningImprovements\Reaction;

use XF\Mvc\Entity\Entity;
use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface as SupportsDisablingReactionEntityInterface;

trait SupportsDisablingReactionTrait
{
    public function reactionsCountedForSvWarnImprov(Entity $entity) : bool
    {
        if (!($entity instanceof SupportsDisablingReactionEntityInterface))
        {
            return true;
        }

        return !$entity->hasDisabledReactionsForSvWarnImprov($entity);
    }
}