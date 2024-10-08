<?php

namespace SV\WarningImprovements\XF\Reaction;

use SV\WarningImprovements\Reaction\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Reaction\SupportsDisablingReactionTrait;
use XF\Mvc\Entity\Entity;

/**
 * @extends \XF\Reaction\ProfilePostComment
 */
class ProfilePostComment extends XFCP_ProfilePostComment implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;

    /** @noinspection PhpMissingReturnTypeInspection */
    public function reactionsCounted(Entity $entity)
    {
        return parent::reactionsCounted($entity)
            && $this->reactionsCountedForSvWarnImprov($entity);
    }
}