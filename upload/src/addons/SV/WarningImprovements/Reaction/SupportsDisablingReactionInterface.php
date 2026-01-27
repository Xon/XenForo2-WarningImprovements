<?php

namespace SV\WarningImprovements\Reaction;

use XF\Mvc\Entity\Entity;

interface SupportsDisablingReactionInterface
{
    public function reactionsCountedForSvWarnImprov(Entity $entity): bool;
}