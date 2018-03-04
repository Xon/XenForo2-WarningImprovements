<?php

namespace SV\WarningImprovements\XF\Repository;

class WarningAction extends XFCP_WarningAction
{
    /** @var null|\SV\WarningImprovements\XF\Entity\WarningAction[] */
    protected $warningActions = null;

    /**
     * @return \SV\WarningImprovements\XF\Entity\WarningAction[]|\XF\Mvc\Entity\ArrayCollection
     */
    public function getCachedActionsList()
    {
        if ($this->warningActions === null)
        {
            $this->warningActions = $this->finder('XF:WarningAction')->fetch();
        }

        return $this->warningActions;
    }
}