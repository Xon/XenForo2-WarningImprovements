<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements;

use SV\Utils\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\User;

class Setup extends AbstractSetup
{
    // from https://github.com/Xon/XenForo2-Utils cloned to src/addons/SV/Utils
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep3()
    {
        $this->cleanupWarningCategories();
    }

    public function installStep4()
    {
        $db = $this->db();

        $db->update('xf_sv_warning_category', ['parent_category_id' => null], 'parent_category_id = ?', 0);

        $db->update('xf_warning_definition', ['sv_warning_category_id' => null], 'sv_warning_category_id = ?', 0);

        $db->update('xf_warning_action', ['sv_warning_category_id' => null], 'sv_warning_category_id = ?', 0);

        $db->update('xf_warning_action', ['sv_post_node_id' => null], 'sv_post_node_id = ?', 0);

        $db->update('xf_warning_action', ['sv_post_thread_id' => null], 'sv_post_thread_id = ?', 0);

        $db->update('xf_warning_action', ['sv_post_as_user_id' => null], 'sv_post_as_user_id = ?', 0);
    }

    public function installStep5()
    {
        $this->renamePhrases([
            'sv_warning_category_*_title' => 'sv_warning_category_title.*'
        ]);
        $this->addDefaultPhrases();
    }

    public function addDefaultPhrases()
    {
        $this->addDefaultPhrase('warning_title.0', 'Custom Warning', true);
        $this->addDefaultPhrase('warning_conv_title.0', '', true);
        $this->addDefaultPhrase('warning_conv_text.0', '', true);
        $this->addDefaultPhrase('sv_warning_category_title.1', 'Warnings', true);
    }

    public function cleanupWarningCategories()
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
        $db->query("INSERT IGNORE
                          INTO xf_sv_warning_category (warning_category_id, parent_category_id, display_order, allowed_user_group_ids)
                          VALUES (1, NULL, 0, ?)
         ", [-1]);

        // set all warning definitions to be in default warning category, note; the phrase is defined in the XML
        $db->query('UPDATE xf_warning_definition
            SET sv_warning_category_id = 1
            WHERE sv_warning_category_id is null OR
                  NOT exists (SELECT *
                              FROM xf_sv_warning_category
                              WHERE xf_warning_definition.sv_warning_category_id = xf_sv_warning_category.warning_category_id)
        ');

        // categories have a summary count of thier warnings
        /** @noinspection SqlWithoutWhere */
        $db->query("UPDATE xf_sv_warning_category
             SET warning_count = (SELECT COUNT(*)
                                  FROM xf_warning_definition
                                  WHERE xf_sv_warning_category.warning_category_id = xf_warning_definition.sv_warning_category_id)");

    }

    public function upgrade2000000Step1()
    {
        $this->renameOption('sv_warningimprovements_continue_button', 'sv_warningimprovements_sticky_button');
    }

    public function upgrade2000000Step2()
    {
        $this->installStep5();
    }

    public function upgrade2010800Step1()
    {
        $this->installStep1();
    }

    public function upgrade2010800Step2()
    {
        $this->installStep2();
    }

    public function upgrade2010800Step3()
    {
        /** @var \XF\repository\UserGroup $userGroupRepo */
        $userGroupRepo = \XF::repository('XF:UserGroup');
        $allGroups = array_keys($userGroupRepo->getUserGroupTitlePairs());
        /** @var \SV\WarningImprovements\Entity\WarningCategory $group */
        foreach(\XF::finder('SV\WarningImprovements:WarningCategory')->fetch() as $group)
        {
            $groups = $group->allowed_user_group_ids;

            if ($groups == $allGroups)
            {
                $groups = [-1];
            }

            $group->allowed_user_group_ids = $groups;
            $group->saveIfChanged();
        }
    }

    public function uninstallStep1()
    {
        $this->db()->query("update xf_warning_definition set expiry_type = 'days' where expiry_type = 'hours' ");
        $this->db()->query("delete from xf_warning_definition where warning_definition_id = 0 ");
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep3()
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    /**
     * Removes items associated with this add-on but not directly owned by it.
     */
    public function uninstallStep4()
    {
        $this->deletePhrases([
            'sv_warning_category_title.%',
            'warning_title.0',
            'warning_conv_title.0',
            'warning_conv_text.0',
        ]);
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        $this->addDefaultPhrases();
        $this->cleanupWarningCategories();
    }

    /**
     * @return array
     */
    protected function getTables()
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

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table)
        {
            $this->addOrChangeColumn($table, 'sv_pending_warning_expiry', 'int')->nullable(true)->setDefault(null);
        };

        $tables['xf_warning_definition'] = function (Alter $table)
        {
            $this->addOrChangeColumn($table, 'sv_warning_category_id', 'int')->nullable(true)->setDefault(null);
            $this->addOrChangeColumn($table, 'sv_display_order', 'int')->setDefault(0);
            $this->addOrChangeColumn($table, 'sv_custom_title', 'tinyint', 1)->setDefault(0);
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

    protected function getRemoveAlterTables()
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table)
        {
            $table->dropColumns('sv_pending_warning_expiry');
        };

        $tables['xf_warning_definition'] = function (Alter $table)
        {
            $table->dropColumns(['sv_warning_category_id', 'sv_display_order', 'sv_custom_title']);
            $table->changeColumn('expiry_type')->removeValues(['hours']);
        };

        $tables['xf_warning_definition'] = function (Alter $table)
        {
            $table->dropColumns('sv_warning_category_id');
        };

        $tables['xf_warning_action'] = function (Alter $table)
        {
            $table->dropColumns(['sv_warning_category_id', 'sv_post_node_id', 'sv_post_thread_id', 'sv_post_as_user_id']);
        };

        return $tables;
    }
}
