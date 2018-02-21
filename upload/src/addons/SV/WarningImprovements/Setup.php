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

        foreach($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
        }
    }


    public function upgrade2000000Step1()
    {
        $sm = $this->schemaManager();

        foreach($this->getTables() as $tableName => $callback)
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
            $table->addColumn('warning_default_id', 'int');
            $table->addColumn('threshold_points', 'smallint')->setDefault(0);
            $table->addColumn('expiry_type', 'enum')->values(['never','days','weeks','months','years'])->setDefault('never');
            $table->addColumn('expiry_extension', 'smallint')->setDefault(0);
            $table->addColumn('active', 'tinyint', 3)->setDefault(1);
            $table->addPrimaryKey('warning_default_id');
        };

        return $tables;
    }
}
