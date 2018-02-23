<?php

namespace SV\WarningImprovements\XF\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Admin\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);

        if ($warnings = $response->getParam('warnings'))
        {
            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->getWarningRepo();
            $escaltingDefaults = $warningRepo->getWarningDefaultExtentions();

            /** @var \SV\WarningImprovements\Finder\WarningCategory $warningCategoryFinder */
            $warningCategoryFinder = $this->finder('SV\WarningImprovements:WarningCategory');
            $warningCategories = $warningCategoryFinder->fetch();

            $response->setParams([
                'escalatingDefaults' => $escaltingDefaults,
                'warningCategories' => $warningCategories
            ]);
        }

        return $response;
    }

    public function actionLoadTree()
    {

    }

    public function actionSyncTree()
    {

    }

    public function actionRenameTreeItem()
    {

    }

    public function actionEdit(ParameterBag $params)
    {
        $response = parent::actionEdit($params);

        return $response;
    }

    public function warningAddEdit(\XF\Entity\WarningDefinition $warning)
    {
        $response = parent::warningAddEdit($warning);

        return $response;
    }

    public function actionSave(ParameterBag $params)
    {
        $response = parent::actionSave($params);

        return $response;
    }

    public function _actionAddEdit(\XF\Entity\WarningAction $action)
    {
        $response = parent::_actionAddEdit($action);

        if ($response instanceof \XF\Mvc\Reply\View)
        {
            /** @var \XF\Repository\Node $nodeRepo */
            $nodeRepo = $this->app()->repository('XF:Node');
            $nodes = $nodeRepo->getFullNodeList()->filterViewable();
            
            $response->setParam('nodeTree', $nodeRepo->createNodeTree($nodes));
        }

        return $response;
    }

    public function defaultActionAddEdit(\SV\WarningImprovements\Entity\WarningDefault $defaultAction)
    {
        $viewParams = [
            'actionAction' => $defaultAction
        ];
        return $this->view('XF:Warning\Action\Edit', 'warning_action_edit', $viewParams);
    }

    public function actionDefaultEdit(ParameterBag $params)
    {
        $defaultAction = $this->assertDefaultExists($params->warning_default_id);

        return $this->defaultActionAddEdit($defaultAction);
    }

    public function actionDefaultAdd()
    {
        /** @var \SV\WarningImprovements\Entity\WarningDefault $defaultAction */
        $defaultAction = $this->em()->create('SV\WarningImprovements:WarningDefault');

        return $this->defaultActionAddEdit($defaultAction);
    }

    protected function defaultSaveProcess(\SV\WarningImprovements\Entity\WarningDefault $defaultAction)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'threshold_points' => 'uint',
            'expiry_extension' => 'uint',
            'expiry_type' => 'str',
            'active' => 'bool'
        ]);
        $form->basicEntitySave($defaultAction, $input);

        return $form;
    }

    public function actionDefaultSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->warning_default_id)
        {
            $defaultAction = $this->assertDefaultExists($params->warning_default_id);
        }
        else
        {
            /** @var \SV\WarningImprovements\Entity\WarningDefault $defaultAction */
            $defaultAction = $this->em()->create('SV\WarningImprovements:WarningDefault');
        }

        $this->defaultSaveProcess($defaultAction)->run();

        return $this->redirect(
            $this->buildLink('warnings') . $this->buildLinkHash('warning_default-' . $defaultAction->getEntityId())
        );
    }

    public function actionDefaultDelete(ParameterBag $params)
    {
        $defaultAction = $this->assertDefaultExists($params->warning_default_id);

        if ($this->isPost())
        {
            $defaultAction->delete();

            return $this->redirect($this->buildLink('warnings'));
        }
        else
        {
            $viewParams = [
                'defaultAction' => $defaultAction
            ];
            return $this->view('XF:Warning\DefaultDelete', 'sv_warningimprovements_warning_default_delete', $viewParams);
        }
    }

    public function warningCategoryAddEdit(\SV\WarningImprovements\Entity\WarningCategory $warningCategory)
    {
        $viewParams = [
            'category' => $warningCategory,
            'categories' => $this->getCategoryRepo()->getWarningCategoryRoots(),
            'userGroups' => $this->repository('XF:UserGroup')->getUserGroupTitlePairs()
        ];
        return $this->view('XF:Warning\Category\Edit', 'sv_warning_category_edit', $viewParams);
    }

    public function actionCategoryAdd()
    {
        /** @var \SV\WarningImprovements\Entity\WarningCategory $warningCategory */
        $warningCategory = $this->em()->create('SV\WarningImprovements:WarningCategory');

        return $this->warningCategoryAddEdit($warningCategory);
    }

    public function actionCategoryEdit(ParameterBag $params)
    {
        $warningCategory = $this->assertCategoryExists($params->warning_category_id);

        return $this->warningCategoryAddEdit($warningCategory);
    }

    protected function categorySaveProcess(\SV\WarningImprovements\Entity\WarningCategory $warningCategory)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'warning_category_id' => 'str',
            'parent_warning_category_id' => 'uint',
            'display_order' => 'uint',
            'allowed_user_group_ids' => 'array-uint'
        ]);

        $form->basicEntitySave($warningCategory, $input);

        $phraseInput = $this->filter([
            'title' => 'str'
        ]);
        $form->validate(function(FormAction $form) use ($phraseInput)
        {
            if ($phraseInput['title'] === '')
            {
                $form->logError(\Xf::phrase('please_enter_valid_title'), 'title');
            }
        });
        $form->apply(function() use ($phraseInput, $warningCategory)
        {
            foreach ($phraseInput AS $type => $text)
            {
                $masterPhrase = $warningCategory->getMasterPhrase($type);
                $masterPhrase->phrase_text = $text;
                $masterPhrase->save();
            }
        });

        return $form;
    }

    public function actionCategorySave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->warning_category_id)
        {
            $warningCategory = $this->assertDefaultExists($params->warning_category_id);
        }
        else
        {
            /** @var \SV\WarningImprovements\Entity\WarningCategory $warningCategory */
            $warningCategory = $this->em()->create('SV\WarningImprovements:WarningCategory');
        }

        $this->categorySaveProcess($warningCategory)->run();

        return $this->redirect(
            $this->buildLink('warnings') . $this->buildLinkHash('warning_default-' . $warningCategory->getEntityId())
        );
    }

    public function actionCategoryDelete(ParameterBag $params)
    {
        $warningCategory = $this->assertDefaultExists($params->warning_category_id);

        if ($this->isPost())
        {
            $warningCategory->delete();

            return $this->redirect($this->buildLink('warnings'));
        }
        else
        {
            $viewParams = [
                'category' => $warningCategory
            ];
            return $this->view('XF:Warning\DefaultDelete', 'sv_warningimprovements_warning_default_delete', $viewParams);
        }
    }

    /**
     * @return \SV\WarningImprovements\Repository\WarningCategory
     */
    protected function getCategoryRepo()
    {
        return $this->repository('SV\WarningImprovements:WarningCategory');
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     *
     * @return \SV\WarningImprovements\Entity\WarningCategory
     */
    protected function assertCategoryExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('SV\WarningImprovements:WarningCategory', $id, $with, $phraseKey);
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     *
     * @return \SV\WarningImprovements\Entity\WarningDefault
     */
    protected function assertDefaultExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('SV\WarningImprovements:WarningDefault', $id, $with, $phraseKey);
    }
}