<?php

namespace SV\WarningImprovements\Job;

use SV\ReportImprovements\Globals;
use XF\Job\AbstractRebuildJob;

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
        $db = $this->app->db();

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
        /** @var \XF\Entity\User|null $user */
        $user = \XF::app()->find('XF:User', $id, ['Option']);
        if ($user === null)
        {
            return;
        }
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = \XF::repository('XF:Warning');
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