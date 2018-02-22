<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\UserChangeTemp
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    protected function _postSave()
    {
        parent::_postSave();

        $this->_getWarningRepo()->updatePendingExpiryFor($this->User, true);
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->_getWarningRepo()->updatePendingExpiryFor($this->User, true);
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\XF\Repository\Warning|\SV\WarningImprovements\XF\Repository\Warning
     */
    protected function _getWarningRepo()
    {
        return $this->repository('XF:Warning');
    }
}
