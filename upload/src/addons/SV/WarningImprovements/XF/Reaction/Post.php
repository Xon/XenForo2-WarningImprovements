<?php

namespace SV\WarningImprovements\XF\Reaction;

use SV\WarningImprovements\Reaction\SupportsDisablingReactionInterface;
use SV\WarningImprovements\Reaction\SupportsDisablingReactionTrait;
use XF\Mvc\Entity\Entity;

/**
 * @extends \XF\Reaction\Post
 */
class Post extends XFCP_Post implements SupportsDisablingReactionInterface
{
    use SupportsDisablingReactionTrait;

    /** @noinspection PhpMissingReturnTypeInspection */
    public function reactionsCounted(Entity $entity)
    {
        return parent::reactionsCounted($entity)
            && $this->reactionsCountedForSvWarnImprov($entity);
    }
}