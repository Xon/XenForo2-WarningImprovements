<?php

namespace SV\WarningImprovements\Alert;

use SV\StandardLib\Helper;
use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Entity\Warning as WarningEntity;
use function in_array;
use function is_array;

class WarningAlert extends AbstractHandler
{
    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function canViewContent(Entity $entity, &$error = null): bool
    {
        return true;
    }

    protected function getPlaceholderWarning(): WarningEntity
    {
        $warning = Helper::createEntity(WarningEntity::class);
        $warning->user_id = \XF::visitor()->user_id;
        $warning->setReadOnly(true);

        return $warning;
    }

    public function getContent($id)
    {
        if ($id instanceof AbstractCollection)
        {
            $id = $id->toArray();
        }

        if (is_array($id))
        {
            $warnings = parent::getContent($id);

            if (in_array(0, $id, true))
            {
                $warningArr = $warnings->toArray();
                $warningArr[0] = $this->getPlaceholderWarning();
                $warnings = new ArrayCollection($warningArr);
            }

            return $warnings;
        }

        if ($id === 0)
        {
            return $this->getPlaceholderWarning();
        }

        return parent::getContent($id);
    }
}