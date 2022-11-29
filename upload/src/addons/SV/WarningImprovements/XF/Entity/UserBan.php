<?php

namespace SV\WarningImprovements\XF\Entity;

/**
 * Extends \XF\Entity\UserBan
 */
class UserBan extends XFCP_UserBan
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert() || $this->isChanged(['end_date', 'triggered']))
        {
            $this->svUpdatePendingExpiry();
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->svUpdatePendingExpiry();
    }

    protected function svUpdatePendingExpiry()
    {
        if ($this->User === null)
        {
            return;
        }

        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->repository('XF:Warning');
        $warningRepo->updatePendingExpiryForLater($this->User, true);
    }
}
