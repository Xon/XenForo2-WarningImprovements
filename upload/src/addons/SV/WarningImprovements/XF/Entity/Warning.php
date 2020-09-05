<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\User as UserExtendedEntity;
use SV\WarningImprovements\XF\Entity\WarningDefinition as WarningDefinitionExtended;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Arr as ArrUtil;

/**
 * @property string                                                 notes_
 *
 * GETTERS
 * @property UserExtendedEntity|\XF\Entity\User|null                      anonymized_issuer
 * @property int                                                    expiry_date_rounded
 * @property \XF\Entity\WarningDefinition                           definition
 * @property string                                                 title_censored
 *
 * RELATIONS
 * @property WarningDefinitionExtended|\XF\Entity\WarningDefinition Definition
 * @property \XF\Entity\WarningDefinition                           Definition_
 * @property \XF\Entity\Report                                      Report
 * @property UserExtendedEntity                                           User
 */
class Warning extends XFCP_Warning
{
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

    public function getTitleCensored() : string
    {
        $title = $this->title;

        /** @var UserExtendedEntity $visitor */
        $visitor = \XF::visitor();
        if ($visitor->canByassWarningTitleCensor())
        {
            return $title;
        }

        $censorListSr = $this->app()->options()->svWarningImprov_censorWarningTitle;
        if (empty($censorListSr))
        {
            return $title;
        }

        $censorList = ArrUtil::stringToArray(
            $censorListSr,
            '/\r?\n/'
        );

        foreach ($censorList AS $phrase)
        {
            $phrase = trim($phrase);
            if (!strlen($phrase))
            {
                continue;
            }

            if ($phrase[0] != '/')
            {
                $phrase = preg_quote($phrase, '#');
                $phrase = str_replace('\\*', '[\w"\'/ \t]*', $phrase);
                $phrase = '#(?<=\W|^)(' . $phrase . ')(?=\W|$)#iu';
            }
            else
            {
                if (preg_match('/\W[\s\w]*e[\s\w]*$/', $phrase))
                {
                    // can't run a /e regex
                    continue;
                }
            }

            try
            {
                $title = \preg_replace($phrase, '', $title);
            }
            catch (\ErrorException $e) {}
        }

        return $title;
    }

    public function canViewNotes()
    {
        $visitor = \XF::visitor();

        return $visitor->user_id && $visitor->hasPermission('general', 'viewWarning');
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function canView(&$error = null)
    {
        $canView = parent::canView($error);
        if ($canView)
        {
            return $canView;
        }

        $visitor = \XF::visitor();
        $userId = $visitor->user_id;

        if (!$userId)
        {
            return false;
        }

        if ($userId === $this->user_id && $this->app()->options()->sv_view_own_warnings)
        {
            return true;
        }

        return false;
    }

    /**
     * @param string|null $error
     * @return bool
     */
    public function canViewIssuer(&$error = null)
    {
        /** @var UserExtendedEntity $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewIssuer($error);
    }

    /**
     * @return UserExtendedEntity|\XF\Entity\User|Entity
     */
    public function getAnonymizedIssuer()
    {
        $anonymizedIssuer = null;

        $options = $this->app()->options();
        if (!empty($options->sv_warningimprovements_warning_user))
        {
            $warningStaff = $this->em()->find('XF:User', $options->sv_warningimprovements_warning_user);
            if ($warningStaff)
            {
                $anonymizedIssuer = $warningStaff;
            }
        }

        if (!$anonymizedIssuer)
        {
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $anonymizedIssuer = $userRepo->getGuestUser(\XF::phrase('WarningStaff')->render());
        }

        return $anonymizedIssuer;
    }

    public function getDefinition()
    {
        if ($this->warning_definition_id === 0)
        {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');

            return $warningRepo->getCustomWarningDefinition();
        }

        return $this->Definition_; // _ = bypass getter
    }

    public function verifyNotes($notes)
    {
        $minNoteLength = (int)\XF::options()->sv_wi_warning_note_chars;
        if ($minNoteLength > 0)
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

    protected function updateUserWarningPoints(\XF\Entity\User $user, $adjustment, $isDelete = false)
    {
        Globals::$warningObj = $this;

        try
        {
            parent::updateUserWarningPoints($user, $adjustment, $isDelete);
        }
        finally
        {
            Globals::$warningObj = null;
        }
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
            $minNoteLength = (int)\XF::options()->sv_wi_warning_note_chars;
            if ($minNoteLength > 0)
            {
                $structure->columns['notes']['required'] = 'sv_please_enter_note_for_warning';
            }
        }

        $structure->getters['anonymized_issuer'] = true;
        $structure->getters['expiry_date_rounded'] = true;
        $structure->getters['Definition'] = false;
        $structure->getters['title_censored'] = true;

        $structure->relations['Report'] = [
            'entity'     => 'XF:Report',
            'type'       => self::TO_ONE,
            'conditions' => [['content_type', '=', '$content_type'], ['content_id', '=', '$content_id']],
        ];

        // translator note: not really, this is just to avoid is_processing throwing template error
        $structure->options['hasCensoredTitle'] = true;

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
