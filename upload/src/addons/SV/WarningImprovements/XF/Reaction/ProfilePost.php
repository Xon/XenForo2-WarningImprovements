<?php

namespace SV\WarningImprovements\XF\Reaction;

use SV\WarningImprovements\Reaction\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Reaction\SupportsDisablingReactionTrait;
use XF\Mvc\Entity\Entity;

/**
 * @extends \XF\Reaction\ProfilePost
 */
class ProfilePost extends XFCP_ProfilePost implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;

    public function reactionsCounted(Entity $entity)
    {
        return parent::reactionsCounted($entity)
            && $this->reactionsCountedForSvWarnImprov($entity);
    }
}