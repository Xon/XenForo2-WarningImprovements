<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\WarningImprovements\Entity;

use XF\Mvc\Entity\Entity;

trait SupportsDisablingReactionTrait
{
    public function hasDisabledReactionsForSvWarnImprov(Entity $entity, &$error = null): bool
    {
        $warningId = $this->warning_id ?? 0;
        if ($warningId === 0)
        {
            return false;
        }

        $embedMetaData = $entity->get('embed_metadata');

        return (bool)($embedMetaData['sv_disable_reactions'] ?? false);
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
        if ($this->hasDisabledReactionsListForSvWarnImprov($this))
        {
            return [];
        }

        return parent::getReactions();
    }
}