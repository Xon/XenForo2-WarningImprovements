<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\Entity;

/**
 * Extends \XF\Entity\UserBan
 */
class UserBan extends XFCP_UserBan
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert() || $this->isChanged('end_date'))
        {
            $this->getWarningRepo()->updatePendingExpiryFor($this->User, true);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->getWarningRepo()->updatePendingExpiryFor($this->User, true);
    }

    /**
     * @return \SV\WarningImprovements\XF\Repository\Warning|\XF\Repository\Warning|\XF\Mvc\Entity\Repository
     */
    protected function getWarningRepo()
    {
        return $this->repository('XF:Warning');
    }
}
