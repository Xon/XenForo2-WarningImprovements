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

        if ($response instanceof \XF\Mvc\Reply\View)
        {
            $categoryRepo = $this->getCategoryRepo();
            $categories = $categoryRepo->findCategoryList()->fetch();
            $categoryTree = $categoryRepo->createCategoryTree($categories);

            $response->setParam('categoryTree', $categoryTree);

            $warningRepo = $this->getWarningRepo();
            $warnings = $warningRepo->findWarningDefinitionsForList()
                ->order('sv_display_order', 'asc')
                ->fetch()
                ->groupBy('sv_warning_category_id');
            $response->setParam('warnings', $warnings);
        }

        return $response;
    }

    public function warningAddEdit(\XF\Entity\WarningDefinition $warning)
    {
        $response = parent::warningAddEdit($warning);

        if ($response instanceof \XF\Mvc\Reply\View)
        {
            $categoryRepo = $this->getCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree();
            $response->setParam('categoryTree', $categoryTree);
        }

        return $response;
    }

    protected function warningSaveProcess(\XF\Entity\WarningDefinition $warning)
    {
        $categoryId = $this->filter('sv_warning_category_id', 'uint');
        \SV\WarningImprovements\Listener::$warningDefinitionCategoryId = $categoryId ?: null;
        return parent::warningSaveProcess($warning);
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($this->isCustomWarning($params->warning_definition_id))
        {
            $customWarningDefinition = $this->getCustomWarningDefinition();

            $this->warningSaveProcess($customWarningDefinition)->run();

            return $this->redirect($this->buildLink('warnings'));
        }

        return parent::actionSave($params);
    }

    public function actionSort()
    {
        $categoryRepo = $this->getCategoryRepo();
        $categories = $categoryRepo->findCategoryList()->fetch();
        $categoryTree = $categoryRepo->createCategoryTree($categories);

        $warningRepo = $this->getWarningRepo();
        $warnings = $warningRepo->findWarningDefinitionsForList()
            ->order('sv_display_order')
            ->fetch()
            ->groupBy('sv_warning_category_id');

        if ($this->isPost())
        {
            /** @var \XF\ControllerPlugin\Sort $sorter */
            $sorter = $this->plugin('XF:Sort');

            foreach ($warnings as $categoryId => $warning)
            {
                $sortTree = $sorter->buildSortTree($this->filter('category-' . $categoryId, 'json-array'));
                $sortedTreeData = $sortTree->getAllData();
                $lastOrder = 0;

                foreach ($sortedTreeData as $warningId => $data)
                {
                    $lastOrder += 5;
                    /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $entry */
                    $entry = $this->em()->findOne('XF:WarningDefinition', ['warning_definition_id', '=', $warningId]);
                    $entry->sv_warning_category_id = $data['parent_id'];
                    $entry->sv_display_order = $lastOrder;
                    $entry->saveIfChanged();
                }
            }

            return $this->redirect($this->buildLink('warnings'));
        }
        else
        {
            $viewParams = [
                'categoryTree' => $categoryTree,
                'warnings' => $warnings,
            ];

            return $this->view(
                'SV\WarningImprovements\XF:WarningCategory\Sort',
                'sv_warning_sort',
                $viewParams
            );
        }
    }

    public function _actionAddEdit(\XF\Entity\WarningAction $action)
    {
        $response = parent::_actionAddEdit($action);

        if ($response instanceof \XF\Mvc\Reply\View)
        {
            /** @var \XF\Repository\Node $nodeRepo */
            $nodeRepo = $this->app()->repository('XF:Node');
            $nodes = $nodeRepo->getFullNodeList()->filterViewable();

            $categoryRepo = $this->getCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree();

            $response->setParams([
                'nodeTree' => $nodeRepo->createNodeTree($nodes),
                'categoryTree' => $categoryTree
            ]);
        }

        return $response;
    }

    // underscore prefix to not be confused with actual controller actions
    protected function _actionSaveProcess(\XF\Entity\WarningAction $action)
    {
        $inputFieldNames = [
            'sv_warning_category_id' => 'uint',
            'sv_post_node_id' => 'uint'
        ];

        $warningActionData = [];
        foreach ($inputFieldNames AS $inputFieldName => $inputFieldFilterName)
        {
            $warningActionData[$inputFieldName] = $this->filter($inputFieldName, $inputFieldFilterName);
            if ($inputFieldFilterName == 'uint' && empty($warningActionData[$inputFieldName]))
            {
                $warningActionData[$inputFieldName] = null;
            }
        }

        \SV\WarningImprovements\Listener::$warningActionData = $warningActionData;

        return parent::_actionSaveProcess($action);
    }

    public function defaultActionAddEdit(\SV\WarningImprovements\Entity\WarningDefault $defaultAction)
    {
        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = $this->app()->repository('XF:Node');
        $nodes = $nodeRepo->getFullNodeList()->filterViewable();

        $categoryRepo = $this->getCategoryRepo();
        $categoryTree = $categoryRepo->createCategoryTree();

        $viewParams = [
            'actionAction' => $defaultAction,
            'nodeTree' => $nodeRepo->createNodeTree($nodes),
            'categoryTree' => $categoryTree
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
        $categoryRepo = $this->getCategoryRepo();
        $categoryTree = $categoryRepo->createCategoryTree();

        $viewParams = [
            'category' => $warningCategory,
            'categoryTree' => $categoryTree,

            'userGroups' => $this->repository('XF:UserGroup')->getUserGroupTitlePairs()
        ];
        return $this->view('XF:Warning\Category\Edit', 'sv_warning_category_edit', $viewParams);
    }

    public function actionCategoryAdd()
    {
        /** @var \SV\WarningImprovements\Entity\WarningCategory $warningCategory */
        $warningCategory = $this->em()->create('SV\WarningImprovements:WarningCategory');

        if ($parentCategoryId = $this->filter('parent_category_id', 'uint'))
        {
            $warningCategory->parent_category_id = $parentCategoryId;
        }

        return $this->warningCategoryAddEdit($warningCategory);
    }

    public function actionCategoryEdit(ParameterBag $params)
    {
        $warningCategory = $this->assertCategoryExists($this->filter('warning_category_id', 'uint'));

        return $this->warningCategoryAddEdit($warningCategory);
    }

    protected function categorySaveProcess(\SV\WarningImprovements\Entity\WarningCategory $warningCategory)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'parent_category_id' => 'uint',
            'display_order' => 'uint',
            'allowed_user_group_ids' => 'array-uint'
        ]);

        if (!$input['parent_category_id'])
        {
            $input['parent_category_id'] = null;
        }

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

        $warningCategoryId = $this->filter('warning_category_id', 'uint');

        if ($warningCategoryId)
        {
            $warningCategory = $this->assertCategoryExists($warningCategoryId);
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
        return $this->getCategoryTreePlugin()->actionDelete($params);
    }

    public function actionCategorySort()
    {
        return $this->getCategoryTreePlugin()->actionSort();
    }

    /**
     * @return \SV\WarningImprovements\XF\ControllerPlugin\WarningCategoryTree
     */
    protected function getCategoryTreePlugin()
    {
        return $this->plugin('SV\WarningImprovements\XF:WarningCategoryTree');
    }

    /**
     * @return \SV\WarningImprovements\XF\ControllerPlugin\WarningTree
     */
    protected function getWarningTreePlugin()
    {
        return $this->plugin('SV\WarningImprovements\XF:WarningTree');
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
     *
     * @throws \XF\Mvc\Reply\Exception
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
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertDefaultExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('SV\WarningImprovements:WarningDefault', $id, $with, $phraseKey);
    }

    /**
     * @param string $id
     * @param null $with
     * @param null $phraseKey
     *
     * @return \SV\WarningImprovements\XF\Entity\WarningDefinition
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertWarningDefinitionExists($id, $with = null, $phraseKey = null)
    {
        if ($this->isCustomWarning($id) && in_array(\XF::app()->router()->routeToController($this->request()->getRoutePath())->getAction(), ['edit', 'save']))
        {
            return $this->getCustomWarningDefinition();
        }
        else
        {
            return $this->assertRecordExists('XF:WarningDefinition', $id, $with, $phraseKey);
        }
    }

    /**
     * @return \SV\WarningImprovements\XF\Entity\WarningDefinition
     */
    private function getCustomWarningDefinition()
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->getWarningRepo();
        return $warningRepo->getCustomWarning();
    }

    /**
     * @return bool
     */
    private function isCustomWarning($warningDefinitionId)
    {
        return ($warningDefinitionId == 0);
    }
}