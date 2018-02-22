<?php

namespace SV\WarningImprovements\XF\Repository;

/**
 * Extends \XF\Repository\UserChangeTemp
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    public function removeExpiredChangesForUser($userId)
    {
        $expired = $this->finder('XF:UserChangeTemp')
                        ->where('expiry_date', '<=', \XF::$time)
                        ->where('expiry_date', '!=', null)
                        ->order('expiry_date')
                        ->fetch(1000);

        /** @var \XF\Service\User\TempChange $changeService */
        $changeService = $this->app()->service('XF:User\TempChange');

        /** @var \XF\Entity\UserChangeTemp $change */
        foreach ($expired AS $change)
        {
            $changeService->expireChange($change);
        }
    }
}
