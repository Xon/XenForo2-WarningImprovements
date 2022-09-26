<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

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

    public function canReact(&$error = null)
    {
        if ($this->hasDisabledReactionsForSvWarnImprov($this, $error))
        {
            return false;
        }

        return parent::canReact($error);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function getReactions()
    {
        if ($this->hasDisabledReactionsForSvWarnImprov($this))
        {
            return [];
        }

        return parent::getReactions();
    }
}