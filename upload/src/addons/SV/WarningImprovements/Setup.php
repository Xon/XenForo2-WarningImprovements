<?php

namespace SV\WarningImprovements;

use SV\ReportImprovements\Job\WarningLogMigration as WarningLogMigrationJob;
use SV\StandardLib\Helper;
use SV\StandardLib\InstallerHelper;
use SV\WarningImprovements\Finder\WarningCategory as WarningCategoryFinder;
use SV\WarningImprovements\Job\NextExpiryRebuild;
use SV\WarningImprovements\Job\NextExpiryRebuild as NextExpiryRebuildJob;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\User;
use XF\Job\Atomic as AtomicJob;
use XF\Job\PermissionRebuild;
use XF\Repository\Option as OptionRepo;
use XF\Repository\UserGroup as UserGroupRepo;
use function array_keys;
use function count;
use function strval;

class Setup extends AbstractSetup
{
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public static $supportedAddOns = [
        'SV/ReportImprovements' => true,
    ];

    public function installStep1(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    public function installStep3(): void
    {
        $this->cleanupWarningCategories();
    }

    public function installStep4(): void
    {
        $db = $this->db();

        $db->update('xf_sv_warning_category', ['parent_category_id' => null], 'parent_category_id = ?', 0);

        $db->update('xf_warning_definition', ['sv_warning_category_id' => null], 'sv_warning_category_id = ?', 0);

        $db->update('xf_warning_action', ['sv_warning_category_id' => null], 'sv_warning_category_id = ?', 0);

        $db->update('xf_warning_action', ['sv_post_node_id' => null], 'sv_post_node_id = ?', 0);

        $db->update('xf_warning_action', ['sv_post_thread_id' => null], 'sv_post_thread_id = ?', 0);

        $db->update('xf_warning_action', ['sv_post_as_user_id' => null], 'sv_post_as_user_id = ?', 0);
    }

    public function installStep5(): void
    {
        $this->renamePhrases([
            'sv_warning_category_*_title' => 'sv_warning_category_title.*'
        ]);
        $this->addDefaultPhrases();
    }

    public function installStep6(): void
    {
        $this->applyDefaultPermissions();
    }

    protected function applyDefaultPermissions(int $previousVersion = 0): bool
    {
        $applied = false;

        if ($previousVersion < 2080000)
        {
            $this->applyGlobalPermissionByGroup('forum', 'bypassSvReactionList', [User::GROUP_MOD, User::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('profilePost', 'bypassSvReactionList', [User::GROUP_MOD, User::GROUP_ADMIN]);
            $applied = true;
        }

        if ($previousVersion !== 0 && $previousVersion < 2080000)
        {
            $this->applyGlobalPermissionByGroup('forum', 'sv_viewWarningActions', [User::GROUP_MOD, User::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('forum', 'viewWarning_issuer', [User::GROUP_MOD, User::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('forum', 'sv_editWarningActions', [User::GROUP_MOD, User::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('forum', 'sv_showAllWarningActions', [User::GROUP_MOD, User::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('forum', 'svManageIssuedWarnings', [User::GROUP_MOD, User::GROUP_ADMIN]);
            $this->applyGlobalPermissionByGroup('forum', 'svBypassWarnTitleCensor', [User::GROUP_MOD, User::GROUP_ADMIN]);
        }

        return $applied;
    }

    public function addDefaultPhrases(): void
    {
        $this->addDefaultPhrase('warning_title.0', 'Custom Warning', true);
        $this->addDefaultPhrase('warning_conv_title.0', '', true);
        $this->addDefaultPhrase('warning_conv_text.0', '', true);
        $this->addDefaultPhrase('sv_warning_category_title.1', 'Warnings', true);
    }

    public function cleanupWarningCategories(): void
    {
        $db = $this->db();

        // insert the defaults for the custom warning. This can't be normally inserted so fiddle with the sql_mode
        $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO'");

        $db->query("INSERT IGNORE INTO xf_warning_definition
                          (warning_definition_id,points_default,expiry_type,expiry_default,extra_user_group_ids,is_editable, sv_custom_title)
                          VALUES
                          (0,1, 'months',1,'',1, 1)");

        $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");

        // create default warning category, do not use the data writer as that requires the rest of the add-on to be setup
        $db->query('INSERT IGNORE
                          INTO xf_sv_warning_category (warning_category_id, parent_category_id, display_order, allowed_user_group_ids, breadcrumb_data)
                          VALUES (1, NULL, 0, ?, ?)
         ', [-1, ':0:{}']);

        // set all warning definitions to be in default warning category, note; the phrase is defined in the XML
        $db->query('UPDATE xf_warning_definition
            SET sv_warning_category_id = 1
            WHERE sv_warning_category_id is null OR
                  NOT exists (SELECT *
                              FROM xf_sv_warning_category
                              WHERE xf_warning_definition.sv_warning_category_id = xf_sv_warning_category.warning_category_id)
        ');

        // categories have a summary count of their warnings
        /** @noinspection SqlWithoutWhere */
        $db->query('UPDATE xf_sv_warning_category
             SET warning_count = (SELECT COUNT(*)
                                  FROM xf_warning_definition
                                  WHERE xf_sv_warning_category.warning_category_id = xf_warning_definition.sv_warning_category_id)');

        // ensure warning actions are not orphaned
        $db->query('UPDATE xf_warning_action
            SET sv_warning_category_id = null
            WHERE sv_warning_category_id is not null AND
                  NOT exists (SELECT *
                              FROM xf_sv_warning_category
                              WHERE xf_warning_action.sv_warning_category_id = xf_sv_warning_category.warning_category_id)
        ');
    }

    public function upgrade2000000Step1(): void
    {
        $this->renameOption('sv_warningimprovements_continue_button', 'sv_warningimprovements_sticky_button');
    }

    public function upgrade2000000Step2(): void
    {
        $this->installStep5();
    }

    public function upgrade2010800Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade2010800Step2(): void
    {
        $this->installStep2();
    }

    public function upgrade2010800Step3(): void
    {
        $userGroupRepo = Helper::repository(UserGroupRepo::class);
        $allGroups = array_keys($userGroupRepo->getUserGroupTitlePairs());
        foreach(Helper::finder(WarningCategoryFinder::class)->fetch() as $group)
        {
            $groups = $group->allowed_user_group_ids;

            if ($groups === $allGroups)
            {
                $groups = [-1];
            }

            $group->allowed_user_group_ids = $groups;
            $group->saveIfChanged();
        }
    }

    public function upgrade2021001Step1(): void
    {
        // convert a number of columns which had 0 default to null
        $this->installStep4();
    }

    public function upgrade2021501Step1(): void
    {
        $this->renamePhrases([
            'Warning_Summary_Title' => 'Warning_Summary.Title',
            'Warning_Summary_Message' => 'Warning_Summary.Message',
            'Warning_Thread_Title' => 'Warning_Thread.Title',
            'Warning_Thread_Message' => 'Warning_Thread.Message',
        ]);
    }

    public function upgrade2050200Step1(): void
    {
        $this->applyGlobalPermission(
            'general',
            'svBypassWarnTitleCensor',
            'general',
            'viewWarning_issuer'
        );
    }

    public function upgrade2060200Step1(): void
    {
        if (!$this->tableExists('xf_sv_warning_log') || empty($addOns['SV/ReportImprovements']))
        {
            return;
        }

        $this->db()->query('
            update xf_sv_warning_log as warnLog 
            join xf_report_comment as reportComment on (reportComment.warning_log_id = warnLog.warning_log_id and warnLog.operation_type = \'new\')
            set warnLog.warning_user_id = reportComment.user_id  
            where warnLog.warning_user_id <> reportComment.user_id
        ');
    }

    public function upgrade2060200Step2(): void
    {
        if (!$this->tableExists('xf_sv_warning_log') || empty($addOns['SV/ReportImprovements']))
        {
            return;
        }

        $this->db()->query('
            update xf_warning as warn
            join xf_sv_warning_log as warnLog on (warn.warning_id = warnLog.warning_id and warnLog.operation_type = \'new\')
            set warn.warning_user_id = warnLog.warning_user_id
            where warn.warning_user_id = warn.user_id and warn.warning_user_id <> warnLog.warning_user_id
        ');
    }

    public function upgrade2060200Step3(): void
    {
        $this->db()->query('
            update xf_warning as warn
            join xf_moderator_log on (
                    warn.content_type = xf_moderator_log.content_type 
                    and warn.content_id = xf_moderator_log.content_id 
                    and warn.warning_date = xf_moderator_log.log_date
                    and xf_moderator_log.action = \'warning_given\'
                )
            set warn.warning_user_id = xf_moderator_log.user_id
            where warn.warning_user_id = warn.user_id and warn.warning_user_id <> xf_moderator_log.user_id
        ');
    }

    public function upgrade1727973315Step1(): void
    {
        $this->installStep2();
    }

    public function upgrade1746864825Step1 (): void
    {
        \XF::db()->query("
            DELETE FROM xf_change_log
            WHERE xf_change_log.content_type = 'user' AND field = 'sv_warning_view'
        ");
    }

    public function uninstallStep1(): void
    {
        $this->db()->query("update xf_warning_definition set expiry_type = 'days' where expiry_type = 'hours' ");
        $this->db()->query('delete from xf_warning_definition where warning_definition_id = 0 ');
    }

    public function uninstallStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep3(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    /**
     * Removes items associated with this add-on but not directly owned by it.
     */
    public function uninstallStep4(): void
    {
        $this->deletePhrases([
            'sv_warning_category_title.%',
            'warning_title.0',
            'warning_conv_title.0',
            'warning_conv_text.0',
            'sv_warning_improvements_warning_spoiler_title.%'
        ]);
    }


    public function postInstall(array &$stateChanges): void
    {
        parent::postInstall($stateChanges);
        $atomicJobs = [];

        $atomicJobs[] = NextExpiryRebuildJob::class;

        if (count($atomicJobs) !== 0)
        {
            \XF::app()->jobManager()->enqueueUnique(
                'warning-improvements-installer',
                AtomicJob::class, ['execute' => $atomicJobs]
            );
        }
    }

    public function postUpgrade($previousVersion, array &$stateChanges): void
    {
        $this->addDefaultPhrases();
        $this->cleanupWarningCategories();

        $previousVersion = (int)$previousVersion;
        parent::postUpgrade($previousVersion, $stateChanges);
        $atomicJobs = [];
        if (Helper::isAddOnActive('SV/ReportImprovements'))
        {
            $atomicJobs[] = WarningLogMigrationJob::class;
        }
        else
        {
            $this->db()->query('DELETE FROM xf_job WHERE execute_class = ?', 'SV\ReportImprovements:WarningLogMigration');
        }

        if ($this->applyDefaultPermissions($previousVersion))
        {
            $atomicJobs[] = PermissionRebuild::class;
        }

        if ($previousVersion < 2080602)
        {
            $atomicJobs[] = NextExpiryRebuild::class;
        }

        if ($previousVersion < 1700328323)
        {
            $optionRepo = Helper::repository(OptionRepo::class);
            $optionRepo->updateOption('svWarningsOnProfileAgeLimit', (int)(\XF::options()->svWarningEscalatingDefaultsLimit ?? 0));
        }

        if (count($atomicJobs) !== 0)
        {
            \XF::app()->jobManager()->enqueueUnique(
                'warning-improvements-installer',
                AtomicJob::class, ['execute' => $atomicJobs]
            );
        }
    }

    protected function getTables(): array
    {
        $tables = [];

        $tables['xf_sv_warning_default'] = function ($table)
        {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'warning_default_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'threshold_points', 'smallint')->setDefault(0);
            $this->addOrChangeColumn($table, 'expiry_type', 'enum')->values(['never', 'hours', 'days', 'weeks', 'months', 'years'])->setDefault('never');
            $this->addOrChangeColumn($table, 'expiry_extension', 'smallint')->setDefault(0);
            $this->addOrChangeColumn($table, 'active', 'tinyint', 3)->setDefault(1);

            $table->addPrimaryKey('warning_default_id');
        };

        $tables['xf_sv_warning_category'] = function ($table)
        {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table, 'warning_category_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table, 'parent_category_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'display_order', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'lft', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'rgt', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'depth', 'smallint', 5)->setDefault(0);
            $this->addOrChangeColumn($table, 'breadcrumb_data', 'blob');
            $this->addOrChangeColumn($table, 'warning_count', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'allowed_user_group_ids', 'varbinary', 255)->setDefault(strval(User::GROUP_REG));

            $table->addPrimaryKey('warning_category_id');
            $table->addKey(['parent_category_id', 'lft']);
            $table->addKey(['lft', 'rgt']);
        };

        return $tables;
    }

    protected function getAlterTables(): array
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table)
        {
            $this->addOrChangeColumn($table, 'sv_pending_warning_expiry', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'sv_warning_view', 'enum')->values(['radio', 'select'])->nullable()->setDefault(null);
        };

        $tables['xf_warning'] = function (Alter $table)
        {
            $this->addOrChangeColumn($table, 'sv_spoiler_contents', 'tinyint', 3)->setDefault(0);
            $this->addOrChangeColumn($table, 'sv_content_spoiler_title', 'mediumtext')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'sv_disable_reactions', 'tinyint', 3)->setDefault(0);
        };

        if ($this->schemaManager()->tableExists('xf_sv_warning_log'))
        {
            $tables['xf_sv_warning_log'] = $tables['xf_warning'];
        }

        $tables['xf_warning_definition'] = function (Alter $table)
        {
            $this->addOrChangeColumn($table, 'sv_warning_category_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'sv_display_order', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'sv_custom_title', 'tinyint', 1)->setDefault(0);
            $this->addOrChangeColumn($table, 'sv_spoiler_contents', 'tinyint', 3)->setDefault(0);
            $this->addOrChangeColumn($table, 'sv_disable_reactions', 'tinyint', 3)->setDefault(0);
            $table->changeColumn('expiry_type')->addValues(['hours']);
        };

        $tables['xf_warning_action'] = function (Alter $table)
        {
            $this->addOrChangeColumn($table, 'sv_warning_category_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'sv_post_node_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'sv_post_thread_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'sv_post_as_user_id', 'int')->nullable(true)->setDefault(null);
        };

        $tables['xf_sv_warning_category'] = function (Alter $table)
        {
            $table->renameColumn('parent_warning_category_id', 'parent_category_id')
                  ->nullable(true)
                  ->setDefault(null);
        };

        return $tables;
    }

    protected function getRemoveAlterTables(): array
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table)
        {
            $table->dropColumns('sv_pending_warning_expiry');
        };

        $tables['xf_warning'] = function (Alter $table)
        {
            $table->dropColumns(['sv_spoiler_contents', 'sv_disable_reactions']);
        };

        $tables['xf_warning_definition'] = function (Alter $table)
        {
            $table->dropColumns(['sv_warning_category_id', 'sv_display_order', 'sv_custom_title', 'sv_spoiler_contents', 'sv_disable_reactions']);
            $table->changeColumn('expiry_type')->removeValues(['hours']);
        };

        $tables['xf_warning_action'] = function (Alter $table)
        {
            $table->dropColumns(['sv_warning_category_id', 'sv_post_node_id', 'sv_post_thread_id', 'sv_post_as_user_id']);
        };

        return $tables;
    }
}
