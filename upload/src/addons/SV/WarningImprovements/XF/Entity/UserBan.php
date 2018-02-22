<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

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
            $this->_getWarningModel()->updatePendingExpiryFor($this->User, true);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->_getWarningModel()->updatePendingExpiryFor($this->User, true);
    }

    /**
     * @return \SV\WarningImprovements\XF\Repository\Warning|\XF\Repository\Warning|\XF\Mvc\Entity\Repository
     */
    protected function _getWarningModel()
    {
        return $this->repository('XF:Warning');
    }
}
