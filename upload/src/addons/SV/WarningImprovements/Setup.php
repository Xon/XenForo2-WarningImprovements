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

        $db->query('
            UPDATE xf_sv_warning_category
            SET parent_warning_category_id = NULL
            WHERE parent_warning_category_id = 0'
        );
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();
        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
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
            $table->addColumn('warning_default_id', 'int');
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
            $table->addColumn('warning_category_id', 'int');
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

        return $tables;
    }
}
