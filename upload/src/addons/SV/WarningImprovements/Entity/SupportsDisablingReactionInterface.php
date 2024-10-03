<?php

namespace SV\WarningImprovements\Entity;

use XF\Mvc\Entity\Entity;
use XF\Phrase;

interface SupportsDisablingReactionInterface
{
    /**
     * @param Entity      $entity
     * @param Phrase|string|null $error
     * @return bool
     */
    public function hasDisabledReactionsForSvWarnImprov(Entity $entity, &$error = null) : bool;

    public function hasDisabledReactionsListForSvWarnImprov(Entity $entity) : bool;
}