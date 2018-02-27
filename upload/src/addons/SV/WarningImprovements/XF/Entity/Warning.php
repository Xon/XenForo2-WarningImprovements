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
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if ($visitor->user_id === $this->user_id && $this->app()->options()->sv_view_own_warnings)
        {
            $handler = $this->getHandler();
            return $handler ? $handler->canViewContent($this) : false;
        }

        return parent::canView($error);
    }

    public function canViewIssuer()
    {
        /** @var \SV\WarningImprovements\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewIssuer();
    }

    public function getAnonymizedIssuer()
    {
        $anonymizedIssuer = $this->em()->create('XF:User');
        $anonymizedIssuer->username = \XF::phrase('WarningStaff')->render();

        if (!empty($anonymizeAsUserId = $this->app()->options()->sv_warningimprovements_warning_user))
        {
            if ($warningStaff = $this->em()->find('XF:User', $anonymizeAsUserId))
            {
                $anonymizedIssuer = $warningStaff;
            }
        }

        return $anonymizedIssuer;
    }

    public function verifyNotes($notes)
    {
        $options = \XF::options();
        if (!empty($minNoteLength = $options->sv_wi_warning_note_chars))
        {
            $noteLength = utf8_strlen($notes);
            if ($noteLength < $minNoteLength)
            {
                $underAmount = $minNoteLength - $noteLength;
                $this->error(\XF::phrase('sv_please_enter_note_with_at_least_x_characters', [
                    'count' => $minNoteLength,
                    'under' => $underAmount
                ]));

                return false;
            }
        }

        return true;
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
            $structure->columns['notes']['required'] = 'sv_please_enter_note_for_warning';
        }

        $structure->getters['anonymized_issuer'] = true;

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
