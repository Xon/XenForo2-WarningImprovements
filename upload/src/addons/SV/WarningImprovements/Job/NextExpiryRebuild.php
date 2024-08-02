<?php

namespace SV\WarningImprovements\Job;

use SV\StandardLib\Helper;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\Entity\User as UserEntity;
use XF\Job\AbstractRebuildJob;
use XF\Repository\Warning as WarningRepo;

/**
 * Class WarningLogMigration
 *
 * @package SV\ReportImprovements\Job
 */
class NextExpiryRebuild extends AbstractRebuildJob
{
    /**
     * @param int $start
     * @param int $batch
     * @return array
     */
    protected function getNextIds($start, $batch): array
    {
        $db = \XF::db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			', $batch
        ), [$start]);
    }

    /**
     * @param int $id
     * @throws \Exception
     */
    protected function rebuildById($id)
    {
        $user = Helper::find(UserEntity::class, $id, ['Option']);
        if ($user === null)
        {
            return;
        }
        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = Helper::repository(WarningRepo::class);
        $warningRepo->updatePendingExpiryFor($user);
    }

    /**
     * @return null
     */
    protected function getStatusType()
    {
        return null;
    }

    public function getStatusMessage(): string
    {
        $actionPhrase = \XF::phrase('users');

        return sprintf('%s... (%s)', $actionPhrase, $this->data['start']);
    }
}