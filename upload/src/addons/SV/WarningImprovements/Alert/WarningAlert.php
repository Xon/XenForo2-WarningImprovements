<?php

namespace SV\WarningImprovements\Alert;

class WarningAlert extends \XF\Alert\AbstractHandler
{
    public function canViewContent(\XF\Mvc\Entity\Entity $entity, &$error = null)
    {
        return true;
    }
}