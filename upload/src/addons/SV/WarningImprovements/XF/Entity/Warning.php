<?php

namespace SV\WarningImprovements\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Extends \XF\Entity\Warning
 */
class Warning extends XFCP_Warning
{
    public function canView(&$error = null)
    {
        return (parent::canView($error) && $this->canViewIssuer());
    }

    public function canViewIssuer()
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        return $visitor->canViewIssuer();
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->fastDeleteAlertsForContent('warning', $this->warning_id);
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $options = \XF::options();
        if ($options->sv_wi_require_warning_notes)
        {
            unset($structure->columns['notes']['default']);
            $structure->columns['notes']['required'] = true;

            if ($options->sv_wi_warning_note_chars)
            {
                $structure->columns['notes']['minLength'] = $options->sv_wi_warning_note_chars;
            }
        }

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
