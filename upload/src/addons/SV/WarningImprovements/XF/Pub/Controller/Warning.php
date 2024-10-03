<?php

namespace SV\WarningImprovements\XF\Pub\Controller;

use SV\ReportImprovements\XF\Entity\Warning as ReportImprovWarningEntity;
use SV\StandardLib\Helper;
use SV\WarningAcknowledgement\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use SV\WarningImprovements\Globals;
use XF\ControllerPlugin\Delete as DeletePlugin;
use XF\ControllerPlugin\Editor as EditorPlugin;
use XF\Entity\DeletionLog as DeletionLogEntity;
use XF\Entity\SessionActivity as SessionActivityEntity;
use XF\Entity\User as UserEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use SV\WarningImprovements\Service\Warning\Editor as EditorService;
use function array_key_exists;
use function array_merge;
use function strlen;

/**
 * @extends \XF\Pub\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    public function actionDelete(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $warning = $this->assertViewableWarning($params->warning_id);
        if (!$warning->canDelete($error))
        {
            return $this->noPermission($error);
        }

        if ($this->filter('send_warning_alert', 'bool'))
        {
            $warning->setOption('svAlertOnDelete', true);
            $warning->setOption('svAlertOnDeleteReason', $this->filter('send_warning_alert_reason', 'str'));
        }

        if ($this->isPost() && !$this->filter('confirm', 'bool'))
        {
            return $this->redirect($this->buildLink('warnings/delete', $warning));
        }

        $plugin = Helper::plugin($this, DeletePlugin::class);

        return $plugin->actionDelete(
            $warning,
            $this->buildLink('warnings/delete', $warning, ['confirm' => 1]),
            $this->buildLink('warnings', $warning),
            $this->buildLink('members', $warning->User) . '#warnings',
            \XF::phrase('svWarningImprov_warning_for_x', ['title' => $warning->title, 'name' => $warning->User->username]),
            'svWarningInfo_warning_info_delete'
        );
    }

    public function actionEdit(ParameterBag $params): AbstractReply
    {
        /** @var ExtendedWarningEntity $warning */
        /** @noinspection PhpUndefinedFieldInspection */
        $warning = $this->assertViewableWarning((int)$params->warning_id);

        if (!$warning->User)
        {
            return $this->error(\XF::phrase('user_who_received_this_warning_no_longer_exists'));
        }

        if (!$warning->canEdit($error))
        {
            return $this->noPermission($error);
        }

        if ($this->isPost())
        {
            $warningEditor = $this->setupWarningEdit($warning);
            if (!$warningEditor->validate($errors))
            {
                throw $this->exception($this->error($errors));
            }
            $warning = $warningEditor->save();
            if ($warning === null)
            {
                return $this->error(\XF::phrase('svWarningImprov_no_changes_to_warning'), 400);
            }

            return $this->redirect($this->buildLink('warnings', $warning));
        }

        $reply = $this->actionIndex($params);

        $reply->setParam('editingTemplate', 'svWarningInfo_warning_info');

        $handler = $warning->getHandler();
        $content = $warning->Content;

        $colDef = $warning->structure()->columns['notes'] ?? [];
        $userNoteRequired = !($colDef['default'] ?? false) || !empty($colDef['required']);
        $reply->setParam('userNoteRequired', $userNoteRequired);

        if ($content !== null && $handler !== null)
        {
            $contentActions = $handler->getAvailableContentActions($content);
            $reply->setParam('contentActions', $contentActions);

            if (array_key_exists('DeletionLog', $content->structure()->relations))
            {
                /** @var DeletionLogEntity $deletionLog */
                $deletionLog = $content->getRelation('DeletionLog');
                if ($deletionLog !== null)
                {
                    $reply->setParam('defaultContentAction', 'delete');
                    $reply->setParam('contentDeleteReason', $deletionLog->delete_reason);
                }
            }

            if ($content->isValidColumn('warning_message') || $content->isValidGetter('warning_message'))
            {
                $reply->setParam('defaultContentAction', 'public');
                $reply->setParam('contentPublicBanner', $content->get('warning_message'));
            }
        }

        return $reply;
    }

    protected function getWarningEditInput(ExtendedWarningEntity $warning, array $args = []): array
    {
        /** @var ExtendedWarningEntity|ReportImprovWarningEntity $warning */
        $warningDefinition = $warning->Definition;

        $isCustom = $warningDefinition === null || $warningDefinition->is_custom;
        $canEditTitle = $isCustom || $warningDefinition->sv_custom_title;
        $canEditPoints = $isCustom || $warningDefinition->is_editable;

        $defaults = [
            'expire' => 'str',
            'expiry_value' => 'uint',
            'expiry_unit' => 'str',
            'notes' => 'str',
            'content_action' => 'str',
            'action_options' => 'array',
            'send_warning_alert' => 'bool',
            'send_warning_alert_reason' => 'str',

            'sv_spoiler_contents' => 'bool',
            'sv_content_spoiler_title' => 'str',
            'sv_disable_reactions' => 'str'
        ];

        if ($canEditTitle)
        {
            $defaults['title'] = 'str';
        }

        if ($canEditPoints)
        {
            $defaults['points_enable'] = 'bool';
            $defaults['points'] = 'uint';
        }

        $addOns = \XF::app()->container('addon.cache');
        if ($addOns['SV/WarningAcknowledgement'] ?? false)
        {
            /** @var ExtendedWarningDefinitionEntity $warningDefinition */
            $canEditWarningAck = $warningDefinition === null || $warningDefinition->sv_allow_acknowledgement;
            if ($canEditWarningAck)
            {
                $defaults['sv_acknowledgement'] = 'str';
                $defaults['sv_suppress_notices'] = 'bool';
                $defaults['sv_user_note'] = 'str';
                $defaults['sv_user_note_html'] = 'str,noclean';
            }
        }

        if ((($addOns['SV/ReportImprovements'] ?? 0) > 2100002) && $warning->canResolveLinkedReport())
        {
            $defaults['resolve_report'] = 'bool';
            $defaults['resolve_alert'] = 'bool';
            $defaults['resolve_alert_comment'] = 'str';
        }

        $args = array_merge($defaults, $args);

        return $this->filter($args);
    }

    protected function applyInput(EditorService $warningEditor, array $input): array
    {
        /** @var ExtendedWarningEntity|ReportImprovWarningEntity $warning */
        $warning = $warningEditor->getWarning();
        if (isset($input['title']))
        {
            $warningEditor->setTitle($input['title']);
        }

        if (isset($input['expire']))
        {
            $warningEditor->setExpiry($input['expire'], $input['expiry_value'] ?? 0, $input['expiry_unit'] ?? '');
        }

        if (isset($input['notes']))
        {
            $warningEditor->setNotes($input['notes']);
        }

        if (($input['points_enable'] ?? false) && isset($input['points']))
        {
            $points = (int)$input['points'];
            $warningEditor->setPoints($points);
        }

        if (isset($input['content_action']))
        {
            $warningEditor->setContentActions($input['content_action'], $input['action_options'] ?? []);
        }

        if ($input['send_warning_alert'] ?? false)
        {
            $warningEditor->setSendAlert(true, $input['send_warning_alert_reason'] ?? '');
        }

        $addOns = \XF::app()->container('addon.cache');
        if ($addOns['SV/WarningAcknowledgement'] ?? false)
        {
            $bbCode = $input['sv_user_note'] ?? '';
            $html = $input['sv_user_note_html'] ?? '';

            if (strlen($bbCode) === 0 && strlen($html) !== 0)
            {
                $editor = Helper::plugin($this, EditorPlugin::class);
                $bbCode = $editor->convertToBbCode($html);
            }

            /** @noinspection HttpUrlsUsage */
            $bbCode = str_replace(['http://https://', 'https://http://'], ['https://', 'http://'], $bbCode);
            $input['sv_user_note'] = $bbCode;

            $warningEditor->setWarningAck($input);
        }

        if ((($addOns['SV/ReportImprovements'] ?? 0) > 2100002) && $warning->canResolveLinkedReport())
        {
            $warningEditor->setCanReopenReport(false);
            $warningEditor->resolveReportFor($input['resolve_report'] ?? false, $input['resolve_alert'] ?? false, $input['resolve_alert_comment'] ?? '');
        }

        $warningEditor->setSpoilerContents(
            $input['sv_spoiler_contents'],
            $input['sv_content_spoiler_title']
        )->setDisableReactions($input['sv_disable_reactions']);

        return $input;
    }

    protected function setupWarningEdit(ExtendedWarningEntity $warning): EditorService
    {
        $warningEditor = Helper::service(EditorService::class, $warning);

        $inputs = $this->getWarningEditInput($warning);
        $this->applyInput($warningEditor, $inputs);

        return $warningEditor;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function getActivityDetails(array $activities)
    {
        /** @var SessionActivityEntity[] $activities */
        $warningIds = [];
        $warnings = [];
        foreach ($activities AS $activity)
        {
            $warningId = $activity->pluckParam('warning_id');
            if ($warningId)
            {
                $warningIds[$warningId] = $warningId;
            }
        }

        if ($warningIds)
        {
            /** @var \XF\Entity\Warning[] $warnings */
            $warnings = Helper::findByIds(\XF\Entity\Warning::class, $warningIds, ['User']);
        }

        $userIds = [];
        foreach ($activities AS $activity)
        {
            $userId = $activity->user_id;
            if ($userId && !Helper::findCached(UserEntity::class, $userId))
            {
                $userIds[$userId] = $userId;
            }
        }

        if ($userIds)
        {
            Helper::findByIds(UserEntity::class, $userIds);
        }

        $router = \XF::app()->router('public');
        $output = [];
        $defaultModPhrase = \XF::phrase('performing_moderation_duties');
        $defaultUserPhrase = \XF::phrase('viewing_members');

        foreach ($activities AS $key => $activity)
        {
            $activityUserId = $activity->user_id;
            if ($activityUserId && $activity->User)
            {
                $user = $activity->User;
                $isMod = $user->is_staff || $user->is_moderator;// || $user->is_admin;
            }
            else
            {
                $isMod = false;
            }
            $defaultPhrase = $isMod
                ? $defaultModPhrase
                : $defaultUserPhrase;

            $visitor = \XF::visitor();
            $warningId = $activity->pluckParam('warning_id');
            $warning = $warningId ? ($warnings[$warningId] ?? null) : null;
            if ($warning && $warning->User)
            {
                $warnedUserId = $warning->user_id;
                Globals::$profileUserId = $warnedUserId && $activityUserId === $warnedUserId ? $warnedUserId : null;
                if ($visitor->canViewWarnings() && $warning->canView())
                {
                    $output[$key] = [
                        'description' => \XF::phrase('sv_viewing_member_warning', ['username' => $warning->User->username]),
                        'title'       => $warning->title,
                        'url'         => $router->buildLink('warnings', $warning),
                    ];
                }
                else
                {
                    $output[$key] = $defaultPhrase;
                }
            }
            else
            {
                $output[$key] = $defaultPhrase;
            }
        }

        return $output;
    }

    protected function assertViewableWarning($id, array $extraWith = [])
    {
        $extraWith[] = 'User';
        $extraWith[] = 'WarnedBy';
        $extraWith[] = 'Report';

        return parent::assertViewableWarning($id, $extraWith);
    }
}