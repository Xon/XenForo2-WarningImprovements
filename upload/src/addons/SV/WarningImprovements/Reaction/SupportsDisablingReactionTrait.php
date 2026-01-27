<?php

namespace SV\WarningImprovements\Reaction;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface as SupportsDisablingReactionEntityInterface;
use XF\Mvc\Entity\Entity;

trait SupportsDisablingReactionTrait
{
    public function reactionsCountedForSvWarnImprov(Entity $entity): bool
    {
        if (!($entity instanceof SupportsDisablingReactionEntityInterface))
        {
            return true;
        }

        return !$entity->hasDisabledReactionsForSvWarnImprov($entity);
    }
}