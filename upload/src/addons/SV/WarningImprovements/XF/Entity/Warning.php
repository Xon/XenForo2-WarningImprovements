<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\WarningImprovements\XF\Entity;

use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\User as UserExtendedEntity;
use SV\WarningImprovements\XF\Entity\WarningDefinition as WarningDefinitionExtended;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Util\Arr as ArrUtil;

/**
 * COLUMNS
 * @property string                                                 notes_
 * @property bool $sv_spoiler_contents
 * @property string $sv_content_spoiler_title
 * @property bool $sv_disable_reactions
 *
 * GETTERS
 * @property UserExtendedEntity|UserEntity|null                     anonymized_issuer
 * @property int                                                    expiry_date_rounded
 * @property \XF\Entity\WarningDefinition                           definition
 * @property string                                                 title_censored
 *
 * RELATIONS
 * @property WarningDefinitionExtended|\XF\Entity\WarningDefinition Definition
 * @property \XF\Entity\WarningDefinition                           Definition_
 * @property \XF\Entity\Report                                      Report
 * @property UserExtendedEntity                                     User
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
        if ($visitor->canBypassWarningTitleCensor())
        {
            return $title;
        }

        $censorList = ArrUtil::stringToArray(
            $this->app()->options()->svWarningImprov_censorWarningTitle ?? '',
            '/\r?\n/'
        );
        if (!\count($censorList))
        {
            return $title;
        }

        foreach ($censorList AS $phrase)
        {
            $phrase = \trim($phrase);
            if (!\strlen($phrase))
            {
                continue;
            }

            if ($phrase[0] !== '/')
            {
                $phrase = \preg_quote($phrase, '#');
                $phrase = \str_replace('\\*', '[\w"\'/ \t]*', $phrase);
                $phrase = '#(?<=\W|^)(' . $phrase . ')(?=\W|$)#iu';
            }
            else
            {
                if (\preg_match('/\W[\s\w]*e[\s\w]*$/', $phrase))
                {
                    // can't run a /e regex
                    continue;
                }
            }

            try
            {
                $title = \preg_replace($phrase, '', $title);
            }
            catch (\Throwable $e) {}
        }

        return $title;
    }

    public function canViewNotes()
    {
        $visitor = \XF::visitor();

        return $visitor->user_id && $visitor->hasPermission('general', 'viewWarning');
    }

    /**
     * @param Phrase|string|null $error
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

        if ($userId === $this->user_id && ($this->app()->options()->sv_view_own_warnings ?? false))
        {
            return true;
        }

        return false;
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canViewIssuer(&$error = null)
    {
        /** @var UserExtendedEntity $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewIssuer($error);
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canDelete(&$error = null)
    {
        $visitor = \XF::visitor();
        $warnedUserId = $this->warning_user_id;
        if ($warnedUserId && $warnedUserId === $visitor->user_id &&
            !$visitor->hasPermission('general', 'manageWarning') &&
            !$visitor->hasPermission('general', 'svManageIssuedWarnings'))
        {
            return false;
        }

        return parent::canDelete($error);
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canEdit(&$error = null)
    {
        if (\is_callable(parent::class.'::canEdit'))
        {
            /** @noinspection PhpUndefinedMethodInspection */
            return parent::canEdit($error);
        }

        return $this->canEditExpiry($error);
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canEditExpiry(&$error = null)
    {
        $visitor = \XF::visitor();
        $warnedUserId = $this->warning_user_id;
        if ($warnedUserId && $warnedUserId === $visitor->user_id &&
            !$visitor->hasPermission('general', 'manageWarning') &&
            !$visitor->hasPermission('general', 'svManageIssuedWarnings'))
        {
            return false;
        }

        return parent::canEditExpiry($error);
    }

    /**
     * @return UserExtendedEntity|UserEntity
     */
    public function getAnonymizedIssuer(): UserEntity
    {
        $warningUserId = (int)($this->app()->options()->sv_warningimprovements_warning_user ?? 0);
        if ($warningUserId)
        {
            /** @var UserExtendedEntity $warningStaff */
            $warningStaff = $this->em()->find('XF:User', $warningUserId);
            if ($warningStaff)
            {
                return $warningStaff;
            }
        }

        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');
        return $userRepo->getGuestUser(\XF::phrase('WarningStaff')->render());
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
        $minNoteLength = (int)(\XF::options()->sv_wi_warning_note_chars ?? 0);
        if ($minNoteLength > 0)
        {
            $noteLength = \utf8_strlen($notes);
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

    protected function updateUserWarningPoints(UserEntity $user, $adjustment, $isDelete = false)
    {
        $oldWarning = Globals::$warningObj ?? null;
        Globals::$warningObj = $this;

        try
        {
            parent::updateUserWarningPoints($user, $adjustment, $isDelete);
        }
        finally
        {
            Globals::$warningObj = $oldWarning;
        }
    }

    protected $svWarningLogged = false;

    protected function onApplication()
    {
        $this->svWarningLogged = true;
        parent::onApplication();
    }

    protected function onExpiration($isDelete)
    {
        $this->svWarningLogged = true;

        if ($this->isUpdate() && $this->isChanged('points'))
        {
            // onExpiration assumes that points never change, as such expiring and editing a warning at the same time.
            $resetPoints = $this->points;
            $this->set('points', 0, ['forceSet' => true]);
            try
            {
                parent::onExpiration($isDelete);
                return;
            }
            finally
            {
                $this->set('points', $resetPoints, ['forceSet' => true]);
            }
        }

        parent::onExpiration($isDelete);
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert() || $this->isChanged(['expiry_date', 'is_expired', 'points']))
        {
            $this->svUpdatePendingExpiry();
        }

        if ($this->isUpdate() && $this->hasChanges())
        {
            if (!$this->svWarningLogged)
            {
                $content = $this->Content;
                // todo log warning edit even if the content has been hard deleted
                if ($content)
                {
                    if ($this->getOption('log_moderator'))
                    {
                        $app = $this->app();
                        /** @noinspection PhpRedundantOptionalArgumentInspection */
                        $language = $app->language(0);
                        $phraseTitle = 'mod_log.'.$this->content_type.'_warning_edited';
                        $text = $language->getPhraseText($phraseTitle);
                        if ($text === false)
                        {
                            // phrase doesn't exist, create one using `mod_log.warning_edited` as a template
                            /** @var \XF\Entity\Phrase $phrase */
                            $phrase = \XF::app()->finder('XF:Phrase')
                                         ->where('title', '=', $phraseTitle)
                                         ->where('language_id', '=', 0)
                                         ->fetchOne();
                            if (!$phrase)
                            {
                                $phraseText = (string)\XF::phrase('mod_log.warning_edited', [
                                    'contentType' => $app->getContentTypePhrase($this->content_type)
                                ]);

                                /** @var \XF\Entity\Phrase $phrase */
                                $phrase = \XF::em()->create('XF:Phrase');
                                $phrase->language_id = 0;
                                $phrase->title = $phraseTitle;
                                $phrase->phrase_text = $phraseText;
                                $phrase->global_cache = false;
                                $phrase->addon_id = 'SV/WarningImprovements';
                                // try to run outside this transaction
                                \XF::runLater(function() use ($phrase) {
                                    try
                                    {
                                        $phrase->save(false);
                                    }
                                    catch(\Exception $e) {}
                                });
                            }
                        }

                        $this->app()->logger()->logModeratorAction($this->content_type, $content, 'warning_edited', [], false);
                    }
                }
            }

            // Editing a warning, or it was expired + edited
            if ((!$this->is_expired || $this->isChanged('is_expired')) && $this->isChanged('points') && $this->User !== null)
            {
                $oldPoints = (int)$this->getPreviousValue('points');
                $diff = $this->points - $oldPoints;

                if ($diff !== 0)
                {
                    // flag as delete, as this reverse more things
                    $this->updateUserWarningPoints($this->User, $diff, true);
                }
            }
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->fastDeleteAlertsForContent('warning', $this->warning_id);

        if ($this->User === null)
        {
            return;
        }

        $this->svUpdatePendingExpiry();

        if ($this->getOption('svAlertOnDelete'))
        {
            $reason = (string)$this->getOption('svAlertOnDeleteReason');

            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = \XF::repository('XF:Warning');
            $warningRepo->sendWarningAlert($this, 'delete', $reason);
        }
    }

    protected function svUpdatePendingExpiry()
    {
        if ($this->User === null)
        {
            return;
        }

        \XF::runOnce('svPendingExpiry.'.$this->user_id, function () {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->repository('XF:Warning');
            $warningRepo->updatePendingExpiryFor($this->User, true);
        });
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $options = \XF::options();
        if ($options->sv_wi_require_warning_notes ?? false)
        {
            unset($structure->columns['notes']['default']);
            $minNoteLength = (int)(\XF::options()->sv_wi_warning_note_chars ?? 0);
            if ($minNoteLength > 0)
            {
                $structure->columns['notes']['required'] = 'sv_please_enter_note_for_warning';
            }
        }

        $structure->columns['sv_spoiler_contents'] = [
            'type' => self::BOOL,
            'default' => false
        ];
        $structure->columns['sv_content_spoiler_title'] = [
            'type' => self::STR,
            'default' => ''
        ];
        $structure->columns['sv_disable_reactions'] = [
            'type' => self::BOOL,
            'default' => false
        ];

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
        $structure->options['svFullEdit'] = false;
        $structure->options['svAlertOnDelete'] = false;
        $structure->options['svAlertOnDeleteReason'] = '';

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
