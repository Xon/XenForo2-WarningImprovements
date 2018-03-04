<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Globals;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\UserChangeTemp
 *
 * @property \XF\Entity\Phrase name
 * @property \XF\Entity\Phrase result
 * @property bool is_expired
 * @property bool is_permanent
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
                $result = 'n_a';

                if (substr($this->action_modifier, 0, 15) === 'warning_action_')
                {
                    $warningActionId = intval(substr($this->action_modifier, 15));

                    /** @var \SV\WarningImprovements\XF\Repository\UserChangeTemp $userGroupRepo */
                    $userGroupRepo = $this->repository('XF:UserChangeTemp');
                    $userGroups = $userGroupRepo->getCachedUserGroupsList();

                    /** @var \SV\WarningImprovements\XF\Repository\WarningAction $warningActionRepo */
                    $warningActionRepo = $this->repository('XF:WarningAction');
                    $warningActions = $warningActionRepo->getCachedActionsList();

                    $userGroupNames = [];

                    if (!empty($warningActions))
                    {
                        /** @var \SV\WarningImprovements\XF\Entity\WarningAction $warningAction */
                        foreach ($warningActions as $warningAction)
                        {
                            if (!empty($warningAction))
                            {
                                if ($warningAction->warning_action_id === $warningActionId)
                                {
                                   if (!empty($warningAction->extra_user_group_ids))
                                   {
                                       foreach ($warningAction->extra_user_group_ids as $extra_user_group_id)
                                       {
                                           if (!empty($userGroups[$extra_user_group_id]))
                                           {
                                               $userGroupNames[] = $userGroups[$extra_user_group_id]->title;
                                           }
                                       }
                                   }
                                }
                            }
                        }
                    }

                    if (!empty($userGroupNames))
                    {
                        $result = implode(',', $userGroupNames);
                    }
                }

                break;

            case 'field':
                $result = ($this->new_value === '1') ? 'yes' : 'no';
                break;
        }

        return \XF::phrase($result);
    }

    /**
     * @return bool
     */
    public function getIsExpired()
    {
        return ($this->expiry_date < \XF::$time && !$this->is_permanent);
    }

    public function getIsPermanent()
    {
        return ($this->expiry_date === null);
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
     * @param string|null $error
     * @return bool
     */
    public function canViewWarningAction(/** @noinspection PhpUnusedParameterInspection */&$error = null)
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($this->action_modifier === 'is_discouraged' && $this->action_type === 'action_type' && !$this->canViewDiscouragedWarningAction($error))
        {
            return false;
        }

        return $visitor->canViewWarningActions($error);
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function canViewNonSummaryWarningAction(/** @noinspection PhpUnusedParameterInspection */&$error = null)
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->canViewNonSummaryWarningActions($error);
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function canViewDiscouragedWarningAction(/** @noinspection PhpUnusedParameterInspection */&$error = null)
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->canViewDiscouragedWarningActions($error);
    }

    /**
     * @param string $error
     * @return bool
     */
    public function canEditWarningAction(/** @noinspection PhpUnusedParameterInspection */&$error = '')
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        return $visitor->canEditWarningActions($error);
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
        $structure->getters['is_permanent'] = true;

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
