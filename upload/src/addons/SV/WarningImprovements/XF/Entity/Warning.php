<?php

namespace SV\WarningImprovements\XF\Entity;

use SV\StandardLib\Helper;
use SV\WarningImprovements\Entity\SupportsEmbedMetadataInterface;
use SV\WarningImprovements\Globals;
use SV\WarningImprovements\XF\Entity\User as UserExtendedEntity;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Entity\Phrase as PhraseEntity;
use XF\Entity\Report as ReportEntity;
use XF\Entity\User as UserEntity;
use XF\Entity\WarningDefinition as WarningDefinitionEntity;
use XF\Finder\Phrase as PhraseFinder;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\UserAlert as UserAlertRepo;
use XF\Repository\Warning as WarningRepo;
use XF\Util\Arr as ArrUtil;
use function array_key_exists;
use function count;
use function is_callable;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function strlen;
use function trim;
use function mb_strlen;

/**
 * @extends \XF\Entity\Warning
 *
 * COLUMNS
 *
 * @property string                                            $notes_
 * @property bool                                                          $sv_spoiler_contents
 * @property string|null                                                   $sv_content_spoiler_title
 * @property bool                                                          $sv_disable_reactions
 * GETTERS
 * @property UserExtendedEntity|UserEntity|null                            $anonymized_issuer
 * @property int                                                           $expiry_date_rounded
 * @property WarningDefinitionEntity                                       $definition
 * @property string                                                        $title_censored
 * @property-read bool                                                     $is_old_warning
 * RELATIONS
 * @property-read ?ExtendedWarningDefinitionEntity|WarningDefinitionEntity $Definition
 * @property-read ?WarningDefinitionEntity                                 $Definition_
 * @property-read ?ReportEntity                                            $Report
 * @property-read ?UserExtendedEntity                                      $User
 */
class Warning extends XFCP_Warning
{
    public function getExpiryDateRounded(): ?int
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
            \XF::app()->options()->svWarningImprov_censorWarningTitle ?? '',
            '/\r?\n/'
        );
        if (count($censorList) === 0)
        {
            return $title;
        }

        foreach ($censorList AS $phrase)
        {
            $phrase = trim($phrase);
            if (strlen($phrase) === 0)
            {
                continue;
            }

            if ($phrase[0] !== '/')
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
                $title = preg_replace($phrase, '', $title);
            }
            catch (\Throwable $e) {}
        }

        return $title;
    }

    public function canViewNotes(): bool
    {
        $visitor = \XF::visitor();

        return $visitor->user_id && $visitor->hasPermission('general', 'viewWarning');
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
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

        if ($userId === $this->user_id && (\XF::app()->options()->sv_view_own_warnings ?? false))
        {
            return true;
        }

        return false;
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     */
    public function canViewIssuer(&$error = null): bool
    {
        /** @var UserExtendedEntity $visitor */
        $visitor = \XF::visitor();

        return $visitor->canViewIssuer($error);
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
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
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function canEdit(&$error = null)
    {
        if (is_callable([parent::class,'canEdit']))
        {
            /** @noinspection PhpUndefinedMethodInspection */
            return parent::canEdit($error);
        }

        return $this->canEditExpiry($error);
    }

    /**
     * @param Phrase|string|null $error
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
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
        $warningUserId = \XF::app()->options()->sv_warningimprovements_warning_user ?? 0;
        if ($warningUserId)
        {
            /** @var UserExtendedEntity $warningStaff */
            $warningStaff = Helper::find(UserEntity::class, $warningUserId);
            if ($warningStaff)
            {
                return $warningStaff;
            }
        }

        $userRepo = Helper::repository(\XF\Repository\User::class);
        return $userRepo->getGuestUser(\XF::phrase('WarningStaff')->render());
    }

    public function getDefinition()
    {
        if ($this->warning_definition_id === 0)
        {
            /** @var ExtendedWarningRepo $warningRepo */
            $warningRepo = Helper::repository(WarningRepo::class);

            return $warningRepo->getCustomWarningDefinition();
        }

        return $this->Definition_; // _ = bypass getter
    }

    protected function getIsOldWarning(): bool
    {
        if (!$this->is_expired && $this->points > 0)
        {
            return false;
        }

        $ageLimit = \XF::options()->svWarningsOnProfileAgeLimit ?? 0;
        if ($ageLimit === 0)
        {
            return false;
        }

        $ageLimit = \XF::$time - $ageLimit * 2629746;
        if ($this->warning_date > $ageLimit)
        {
            return false;
        }

        return true;
    }

    public function verifyNotes(string &$notes): bool
    {
        $minNoteLength = \XF::options()->sv_wi_warning_note_chars ?? 0;
        if ($minNoteLength > 0)
        {
            $noteLength = mb_strlen($notes);
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

    protected function _preSave()
    {
        if (!$this->sv_spoiler_contents || $this->sv_content_spoiler_title === '')
        {
            $this->sv_content_spoiler_title = null;
        }

        parent::_preSave();
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
                        $app = \XF::app();
                        /** @noinspection PhpRedundantOptionalArgumentInspection */
                        $language = $app->language(0);
                        $phraseTitle = 'mod_log.'.$this->content_type.'_warning_edited';
                        $text = $language->getPhraseText($phraseTitle);
                        if ($text === false)
                        {
                            // phrase doesn't exist, create one using `mod_log.warning_edited` as a template
                            $phrase = Helper::finder(PhraseFinder::class)
                                         ->where('title', '=', $phraseTitle)
                                         ->where('language_id', '=', 0)
                                         ->fetchOne();
                            if ($phrase === null)
                            {
                                $phraseText = (string)\XF::phrase('mod_log.warning_edited', [
                                    'contentType' => $app->getContentTypePhrase($this->content_type)
                                ]);

                                $phrase = Helper::createEntity(PhraseEntity::class);
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

                        \XF::app()->logger()->logModeratorAction($this->content_type, $content, 'warning_edited', [], false);
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

        $this->svDisableWarningEmbedding();

        $alertRepo = Helper::repository(UserAlertRepo::class);
        $alertRepo->fastDeleteAlertsForContent('warning', $this->warning_id);

        if ($this->User === null)
        {
            return;
        }

        $this->svUpdatePendingExpiry();

        if ($this->getOption('svAlertOnDelete'))
        {
            $reason = (string)$this->getOption('svAlertOnDeleteReason');

            /** @var ExtendedWarningRepo $warningRepo */
            $warningRepo = Helper::repository(WarningRepo::class);
            $warningRepo->sendWarningAlert($this, 'delete', $reason);
        }
    }

    protected function svDisableWarningEmbedding()
    {
        $content = $this->Content;
        if ($content instanceof SupportsEmbedMetadataInterface)
        {
            $embedMetadata = $content->embed_metadata ?? [];
            unset($embedMetadata['sv_spoiler_contents']);
            unset($embedMetadata['sv_content_spoiler_title']);
            unset($embedMetadata['sv_disable_reactions']);
            $content->embed_metadata = $embedMetadata;
            if ($content->hasOption('svCopyWarningEmbedData'))
            {
                $content->setOption('svCopyWarningEmbedData', false);
            }
            $content->saveIfChanged($saved, true, false);
        }
    }

    protected function svUpdatePendingExpiry()
    {
        if ($this->User === null)
        {
            return;
        }

        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = Helper::repository(WarningRepo::class);
        $warningRepo->updatePendingExpiryForLater($this->User, true);
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        // disable silent truncation to avoid unexpected data loss
        $structure->columns['title']['forced'] = false;
        // prevent null from leaking out of the title field
        // this can happen when the warning title is too long
        if (!array_key_exists('default', $structure->columns['title']))
        {
            $structure->columns['title']['default'] = '';
        }

        $options = \XF::options();
        if ($options->sv_wi_require_warning_notes ?? false)
        {
            unset($structure->columns['notes']['default']);
            $minNoteLength = \XF::options()->sv_wi_warning_note_chars ?? 0;
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
            'nullable' => true,
            'default' => null,
        ];
        $structure->columns['sv_disable_reactions'] = [
            'type' => self::BOOL,
            'default' => false
        ];

        $structure->getters['anonymized_issuer'] = true;
        $structure->getters['expiry_date_rounded'] = true;
        $structure->getters['Definition'] = false;
        $structure->getters['title_censored'] = true;
        $structure->getters['is_old_warning'] = ['getter' => 'getIsOldWarning', 'cache' => true];

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

    protected function _getWarningRepo(): ExtendedWarningRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Helper::repository(WarningRepo::class);
    }
}
