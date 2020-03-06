<?php

namespace SV\WarningImprovements\Job;

use XF\Job\AbstractJob;

class CategoryDelete extends AbstractJob
{
    protected $defaultData = [
        'warning_category_id' => null,
        'warningsComplete'    => false,
        'actionsComplete'     => false,
        'count'               => 0,
        'total'               => null
    ];

    public function run($maxRunTime)
    {
        $s = microtime(true);

        if (!$this->data['warning_category_id'])
        {
            throw new \InvalidArgumentException(\XF::phrase('sv_warning_improvements_cannot_delete_contents_without_category_id'));
        }

        if ($this->data['warningsComplete'] && $this->data['actionsComplete'])
        {
            return $this->complete();
        }

        /** @var \XF\Finder\WarningDefinition|\XF\Mvc\Entity\Finder $warningDefinitionFinder */
        $warningDefinitionFinder = $this->app->finder('XF:WarningDefinition')
                                             ->where('sv_warning_category_id', '=', $this->data['warning_category_id']);
        $warningDefinitionTotal = $warningDefinitionFinder->total();

        /** @var \XF\Finder\WarningAction|\XF\Mvc\Entity\Finder $actionFinder */
        $actionFinder = $this->app->finder('XF:WarningAction')
                                  ->where('sv_warning_category_id', '=', $this->data['warning_category_id']);
        $actionTotal = $actionFinder->total();

        if ($this->data['total'] === null)
        {
            $this->data['total'] = $warningDefinitionTotal + $actionTotal;
            if (!$this->data['total'])
            {
                return $this->complete();
            }
        }

        if (!$this->data['warningsComplete'])
        {
            if (!$warningDefinitionTotal)
            {
                $this->data['warningsComplete'] = true;
                if ($this->data['actionsComplete'])
                {
                    return true;
                }
            }

            $warningIds = $warningDefinitionFinder->pluckFrom('warning_definition_id')->fetch(1000);
            foreach ($warningIds AS $warningId)
            {
                $this->data['count']++;

                /** @var \XF\Entity\WarningDefinition $warningDefinition */
                $warningDefinition = $this->app->find('XF:WarningDefinition', $warningId);
                if (!$warningDefinition)
                {
                    continue;
                }
                $warningDefinition->delete(false);

                if ($maxRunTime && microtime(true) - $s > $maxRunTime)
                {
                    break;
                }
            }
        }

        if (!$this->data['actionsComplete'])
        {
            if (!$actionTotal)
            {
                $this->data['actionsComplete'] = true;
                if ($this->data['warningsComplete'])
                {
                    return true;
                }
            }

            $actionIds = $actionFinder->pluckFrom('warning_action_id')->fetch(1000);
            foreach ($actionIds AS $actionId)
            {
                $this->data['count']++;

                /** @var \XF\Entity\WarningAction $warningAction */
                $warningAction = $this->app->find('XF:WarningAction', $actionId);
                if (!$warningAction)
                {
                    continue;
                }
                $warningAction->delete(false);

                if ($maxRunTime && microtime(true) - $s > $maxRunTime)
                {
                    break;
                }
            }
        }

        return $this->resume();
    }

    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('deleting');
        $typePhrase = \XF::phrase('xfmg_category_contents');

        return sprintf('%s... %s (%s/%s)', $actionPhrase, $typePhrase,
                       \XF::language()->numberFormat($this->data['count']), \XF::language()->numberFormat($this->data['total'])
        );
    }

    public function canCancel()
    {
        return true;
    }

    public function canTriggerByChoice()
    {
        return true;
    }
}