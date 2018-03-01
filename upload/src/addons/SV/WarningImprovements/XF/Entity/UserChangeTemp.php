<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\UserChangeTemp
 *
 * @property \XF\Entity\Phrase name
 * @property \XF\Entity\Phrase result
 * @property bool is_expired
 * @property int expiry_date_rounded
 */
class UserChangeTemp extends XFCP_UserChangeTemp
{
    /**
     * @return \XF\Phrase
     */
    public function getName()
    {
        $name = 'n_a';

        switch ($this->action_type)
        {
            case 'groups':
                $name = 'sv_warning_action_added_to_user_groups';
                break;

            case 'field':
                $name = 'discouraged';
                break;
        }

        return \XF::phrase($name);
    }

    /**
     * @return \XF\Phrase
     */
    public function getResult()
    {
        $result = 'n_a';

        switch ($this->action_type)
        {
            case 'groups':
                $result = 'sv_warning_action_added_to_user_groups';
                break;

            case 'field':
                $result = $this->new_value;
                break;
        }

        return \XF::phrase($result);
    }

    /**
     * @return bool
     */
    public function getIsExpired()
    {
        return ($this->expiry_date < \XF::$time);
    }

    /**
     * @return int|null
     */
    public function getExpiryDateRounded()
    {
        $visitor = \XF::visitor();

        $expiryDateRound = $this->expiry_date;
        if (!$visitor->user_id ||
            $visitor->hasPermission('general', 'viewWarning'))
        {
            return $expiryDateRound;
        }

        if (!empty($expiryDateRound))
        {
            $expiryDateRound = ($expiryDateRound - ($expiryDateRound % 3600)) + 3600;
        }

        return $expiryDateRound;
    }

    /**
     * @param string $error
     * @return bool
     */
    public function canEditWarningActions(/** @noinspection PhpUnusedParameterInspection */&$error = '')
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('general', 'sv_editWarningActions');
    }

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

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['name'] = true;
        $structure->getters['result'] = true;
        $structure->getters['is_expired'] = true;
        $structure->getters['expiry_date_rounded'] = true;

        return $structure;
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\XF\Repository\Warning|\SV\WarningImprovements\XF\Repository\Warning
     */
    protected function _getWarningRepo()
    {
        return $this->repository('XF:Warning');
    }
}
