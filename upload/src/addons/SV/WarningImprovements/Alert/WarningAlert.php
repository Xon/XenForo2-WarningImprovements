<?php

namespace SV\WarningImprovements\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;

class WarningAlert extends AbstractHandler
{
    public function canViewContent(Entity $entity, &$error = null)
    {
        return true;
    }
}