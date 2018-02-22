<?php

namespace SV\WarningImprovements;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $sm = $this->schemaManager();
        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
        }
        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep2()
    {
        $db = $this->db();
        // insert the defaults for the custom warning. This can't be normally inserted so fiddle with the sql_mode
        $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO'");
        $db->query("INSERT IGNORE INTO xf_warning_definition
                    (warning_definition_id,points_default,expiry_type,expiry_default,extra_user_group_ids,is_editable, sv_custom_title)
                VALUES
                    (0,1, 'months',1,'',1, 1);
            ");
        $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
    }

    public function installStep3()
    {
        $db = $this->db();
        $defaultRegisteredGroupId = 2;
        // create default warning category, do not use the data writer as that requires the rest of the add-on to be setup
        $db->query("insert ignore into xf_sv_warning_category (warning_category_id, parent_warning_category_id, display_order, allowed_user_group_ids)
                values (1, 0, 0, {$defaultRegisteredGroupId})
            ");
    }

    public function installStep4()
    {
        $db = $this->db();
        // set all warning definitions to be in default warning category, note; the phrase is defined in the XML
        $db->query('UPDATE xf_warning_definition
            SET sv_warning_category_id = 1
            WHERE sv_warning_category_id = 0 OR
                  NOT exists (SELECT *
                              FROM xf_sv_warning_category
                              WHERE xf_warning_definition.sv_warning_category_id = xf_sv_warning_category.warning_category_id)
        ');
    }

    public function upgrade2000000Step1()
    {
        $db = $this->db();

        $this->installStep1();

        $sm = $this->schemaManager();
        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }

        $db->query('
            UPDATE xf_sv_warning_category
            SET parent_warning_category_id = NULL
            WHERE parent_warning_category_id = 0'
        );

        $this->installStep4();
    }

    public function upgrade2000000Step2()
    {
        $this->installStep4();
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();
        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();
        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    /**
     * @return array
     */
    protected function getTables()
    {
        $tables = [];

        $tables['xf_sv_warning_default'] = function ($table) {
            /** @var Create|Alter $table */
            if ($table instanceof Create)
            {
                $table->checkExists(true);
            }
            $table->addColumn('warning_default_id', 'int')->autoIncrement();
            $table->addColumn('threshold_points', 'smallint')->setDefault(0);
            $table->addColumn('expiry_type', 'enum')->values(['never', 'days', 'weeks', 'months', 'years'])->setDefault('never');
            $table->addColumn('expiry_extension', 'smallint')->setDefault(0);
            $table->addColumn('active', 'tinyint', 3)->setDefault(1);
            $table->addPrimaryKey('warning_default_id');
        };

        $tables['xf_sv_warning_category'] = function ($table) {
            /** @var Create|Alter $table */
            if ($table instanceof Create)
            {
                $table->checkExists(true);
            }
            $table->addColumn('warning_category_id', 'int')->autoIncrement();
            $table->addColumn('parent_warning_category_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addColumn('allowed_user_group_ids', 'varbinary', 255)->setDefault('2');
            $table->addPrimaryKey('warning_category_id');
        };

        return $tables;
    }

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table) {
            $table->addColumn('sv_pending_warning_expiry', 'int')->nullable(true)->setDefault(null);
        };

        $tables['xf_warning_definition'] = function (Alter $table) {
            $table->addColumn('sv_warning_category_id', 'int')->setDefault(0);
            $table->addColumn('sv_display_order', 'int')->setDefault(0);
            $table->addColumn('sv_custom_title', 'tinyint', 1)->setDefault(0);
        };

        $tables['xf_warning_action'] = function (Alter $table) {
            $table->addColumn('sv_warning_category_id', 'int')->setDefault(0);
            $table->addColumn('sv_post_node_id', 'int')->setDefault(0);
            $table->addColumn('sv_post_thread_id', 'int')->setDefault(0);
            $table->addColumn('sv_post_as_user_id', 'int')->setDefault(0);
        };

        return $tables;
    }

    protected function getRemoveAlterTables()
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table) {
            $table->dropColumns('sv_pending_warning_expiry');
        };

        $tables['xf_warning_definition'] = function (Alter $table) {
            $table->dropColumns(['sv_warning_category_id', 'sv_display_order', 'sv_custom_title']);
        };

        $tables['xf_warning_definition'] = function (Alter $table) {
            $table->dropColumns('sv_warning_category_id');
        };

        $tables['xf_warning_action'] = function (Alter $table) {
            $table->dropColumns(['sv_warning_category_id', 'sv_post_node_id', 'sv_post_thread_id', 'sv_post_as_user_id']);
        };

        return $tables;
    }
}
