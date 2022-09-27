<?php

namespace SV\WarningImprovements\Entity;

use XF\Mvc\Entity\Entity;

trait SupportsDisablingReactionTrait
{
    public function hasDisabledReactionsForSvWarnImprov(Entity $entity, &$error = null) : bool
    {
        if (!$entity->get('warning_id'))
        {
            return false;
        }

        return isset($entity->get('embed_metadata')['sv_disable_reactions']);
    }
}