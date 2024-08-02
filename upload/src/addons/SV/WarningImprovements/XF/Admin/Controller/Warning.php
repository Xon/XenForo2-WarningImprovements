<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpUnusedParameterInspection
 */

namespace SV\WarningImprovements\XF\Admin\Controller;

use SV\StandardLib\Helper;
use SV\WarningImprovements\Entity\WarningCategory;
use SV\WarningImprovements\Entity\WarningDefault;
use XF\Entity\WarningAction;
use XF\Entity\WarningDefinition;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

/**
 * @Extends \XF\Admin\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    public function actionIndex(ParameterBag $params)
    {
        if (!$this->isPost())
        {
            try
            {
                $addOn = new \XF\AddOn\AddOn('SV\WarningImprovements', \XF::app()->addOnManager());

                $setup = new \SV\WarningImprovements\Setup($addOn, \XF::app());
                $setup->addDefaultPhrases();
                $setup->cleanupWarningCategories();
            }
            catch(\Exception $e)
            {
                // swallow exceptions
            }
        }

        $response = parent::actionIndex($params);

        if ($response instanceof View)
        {
            $categoryRepo = $this->getCategoryRepo();
            $categories = $categoryRepo->findCategoryList()->fetch();
            $categoryTree = $categoryRepo->createCategoryTree($categories);

            /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
            $warningRepo = $this->getWarningRepo();
            $warnings = $warningRepo->findWarningDefinitionsForListGroupedByCategory();

            $actions = $warningRepo->findWarningActionsForList()
                                   ->fetch()
                                   ->groupBy('sv_warning_category_id');

            $globalActions = [];

            if (!empty($actions['']))
            {
                $globalActions = $actions[''];
                unset($actions['']);
            }

            $escalatingDefaults = Helper::finder(\SV\WarningImprovements\Finder\WarningDefault::class)->fetch();

            $response->setParams(
                [
                    'categoryTree' => $categoryTree,

                    'warnings' => $warnings,

                    'actions'       => $actions,
                    'globalActions' => $globalActions,

                    'escalatingDefaults' => $escalatingDefaults
                ]);
        }

        return $response;
    }

    public function actionEdit(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $warningDefinitionId = (int)$params->warning_definition_id;
        if ($warningDefinitionId === 0)
        {
            $warning = $this->getCustomWarningDefinition();
        }
        else
        {
            $warning = $this->assertWarningDefinitionExists($warningDefinitionId);
        }

        return $this->warningAddEdit($warning);
    }

    public function warningAddEdit(WarningDefinition $warning)
    {
        $response = parent::warningAddEdit($warning);

        if ($response instanceof View)
        {
            $categoryRepo = $this->getCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree();
            $response->setParams(
                [
                    'categoryTree' => $categoryTree
                ]);
        }

        return $response;
    }

    protected function warningSaveProcess(WarningDefinition $warning)
    {
        if ($this->filter('is_custom', 'bool'))
        {
            $warning = $this->getCustomWarningDefinition();
        }

        $formAction = parent::warningSaveProcess($warning);

        $formAction->setupEntityInput($warning, $this->filter([
            'sv_warning_category_id' => 'uint',
            'sv_custom_title' => 'bool',
            'sv_display_order' => 'uint',

            'sv_spoiler_contents' => 'bool',
            'sv_disable_reactions' => 'bool'
        ]));

        $phraseInput = $this->filter([
            'sv_content_spoiler_title' => 'str'
        ]);
        $formAction->apply(function () use($phraseInput, $warning)
        {
            if (strlen($phraseInput['sv_content_spoiler_title']) === 0)
            {
                $masterContentSpoilerTitle = $warning->getRelation('SvMasterContentSpoilerTitle');
                if ($masterContentSpoilerTitle !== null)
                {
                    $masterContentSpoilerTitle->delete();
                }
            }
            else
            {
                /** @var \XF\Entity\Phrase $masterContentSpoilerTitle */
                $masterContentSpoilerTitle = $warning->getRelationOrDefault('SvMasterContentSpoilerTitle', false);
                $masterContentSpoilerTitle->addon_id = '';
                $masterContentSpoilerTitle->phrase_text = $phraseInput['sv_content_spoiler_title'];
                $masterContentSpoilerTitle->save();
            }
        });

        return $formAction;
    }

    public function actionSort()
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->getWarningRepo();
        $warnings = $warningRepo->findWarningDefinitionsForListGroupedByCategory();

        if ($this->isPost())
        {
            $sorter = Helper::plugin($this,\XF\ControllerPlugin\Sort::class);

            $categoryRepo = $this->getCategoryRepo();
            $categories = $categoryRepo->findCategoryList();

            /** @var WarningCategory $category */
            foreach ($categories as $category)
            {
                $sortTree = $sorter->buildSortTree($this->filter('category-' . $category->warning_category_id, 'json-array'));

                $sortedTreeData = $sortTree->getAllData();
                $lastOrder = 0;

                foreach ($sortedTreeData as $warningId => $data)
                {
                    $lastOrder += 5;
                    /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $entry */
                    $entry = Helper::finder(WarningDefinition::class)
                                   ->where('warning_definition_id', '=', $warningId)
                                   ->fetchOne();
                    $entry->sv_warning_category_id = $data['parent_id'];
                    $entry->sv_display_order = $lastOrder;
                    $entry->saveIfChanged();
                }
            }

            return $this->redirect($this->buildLink('warnings'));
        }
        else
        {
            $categoryRepo = $this->getCategoryRepo();
            $categories = $categoryRepo->findCategoryList()->fetch();
            $categoryTree = $categoryRepo->createCategoryTree($categories);

            $viewParams = [
                'categoryTree' => $categoryTree,
                'warnings'     => $warnings
            ];

            return $this->view(
                'SV\WarningImprovements\XF:WarningCategory\Sort',
                'sv_warning_sort',
                $viewParams
            );
        }
    }

    public function actionDelete(ParameterBag $params)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        if ($params->warning_definition_id === 0)
        {
            return $this->error(\XF::phrase('sv_warning_improvements_custom_warning_cannot_be_deleted'));
        }

        return parent::actionDelete($params);
    }

    public function _actionAddEdit(WarningAction $action)
    {
        $response = parent::_actionAddEdit($action);

        if ($response instanceof View)
        {
            $nodeRepo = Helper::repository(\XF\Repository\Node::class);
            $nodes = $nodeRepo->getFullNodeList()->filterViewable();

            $categoryRepo = $this->getCategoryRepo();
            $categoryTree = $categoryRepo->createCategoryTree();

            $response->setParams(
                [
                    'nodeTree'     => $nodeRepo->createNodeTree($nodes),
                    'categoryTree' => $categoryTree
                ]);
        }

        return $response;
    }

    // underscore prefix to not be confused with actual controller actions
    protected function _actionSaveProcess(WarningAction $action)
    {
        $inputFieldNames = [
            'sv_warning_category_id' => 'uint',
            'sv_post_node_id'        => 'uint',
            'sv_post_thread_id'      => 'uint',
            'sv_post_as_user_id'     => 'uint'
        ];

        foreach ($inputFieldNames AS $inputFieldName => $inputFieldFilterName)
        {
            $input = $this->filter($inputFieldName, $inputFieldFilterName);
            if ($inputFieldFilterName === 'uint' && $input === 0)
            {
                $input = null;
            }
            $action->set($inputFieldName, $input);
        }

        return parent::_actionSaveProcess($action);
    }

    public function defaultActionAddEdit(WarningDefault $defaultAction)
    {
        $nodeRepo = Helper::repository(\XF\Repository\Node::class);
        $nodes = $nodeRepo->getFullNodeList()->filterViewable();

        $categoryRepo = $this->getCategoryRepo();
        $categoryTree = $categoryRepo->createCategoryTree();

        $viewParams = [
            'default'      => $defaultAction,
            'nodeTree'     => $nodeRepo->createNodeTree($nodes),
            'categoryTree' => $categoryTree
        ];

        return $this->view('SV\WarningImprovements\XF:Warning\Action\DefaultEdit', 'sv_warningimprovements_warning_default_edit', $viewParams);
    }

    public function actionDefaultEdit(ParameterBag $params)
    {
        $defaultAction = $this->assertDefaultExists($this->filter('warning_default_id', 'uint'));

        return $this->defaultActionAddEdit($defaultAction);
    }

    public function actionDefaultAdd()
    {
        $defaultAction = Helper::createEntity(WarningDefault::class);

        return $this->defaultActionAddEdit($defaultAction);
    }

    protected function defaultSaveProcess(WarningDefault $defaultAction)
    {
        $form = $this->formAction();

        $input = $this->filter(
            [
                'threshold_points' => 'uint',
                'expiry_extension' => 'uint',
                'expiry_type'      => 'str',
                'active'           => 'bool'
            ]);

        if ($this->filter('expiry_type_base', 'str') === 'never')
        {
            $input['expiry_type'] = 'never';
        }

        $form->basicEntitySave($defaultAction, $input);

        return $form;
    }

    public function actionDefaultSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($warningDefaultId = $this->filter('warning_default_id', 'uint'))
        {
            $defaultAction = $this->assertDefaultExists($warningDefaultId);
        }
        else
        {
            $defaultAction = Helper::createEntity(WarningDefault::class);
        }

        $this->defaultSaveProcess($defaultAction)->run();

        return $this->redirect(
            $this->buildLink('warnings') . $this->buildLinkHash('warning_default-' . $defaultAction->getEntityId())
        );
    }

    public function actionDefaultDelete(ParameterBag $params)
    {
        $defaultAction = $this->assertDefaultExists($this->filter('warning_default_id', 'uint'));

        if ($this->isPost())
        {
            $defaultAction->delete();

            return $this->redirect($this->buildLink('warnings'));
        }
        else
        {
            $viewParams = [
                'default' => $defaultAction
            ];

            return $this->view('XF:Warning\DefaultDelete', 'sv_warningimprovements_warning_default_delete', $viewParams);
        }
    }

    public function warningCategoryAddEdit(WarningCategory $warningCategory)
    {
        $categoryRepo = $this->getCategoryRepo();
        $categoryTree = $categoryRepo->createCategoryTree();

        $viewParams = [
            'category'     => $warningCategory,
            'categoryTree' => $categoryTree,
        ];

        return $this->view('XF:Warning\Category\Edit', 'sv_warning_category_edit', $viewParams);
    }

    public function actionCategoryAdd()
    {
        $warningCategory = Helper::createEntity(WarningCategory::class);

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

    protected function categorySaveProcess(WarningCategory $warningCategory)
    {
        $form = $this->formAction();

        $input = $this->filter(
            [
                'parent_category_id'     => 'uint',
                'display_order'          => 'uint',
            ]);

        if (!$input['parent_category_id'])
        {
            $input['parent_category_id'] = null;
        }

        $usableUserGroups = $this->filter('usable_user_group', 'str');
        if ($usableUserGroups === 'all')
        {
            $input['allowed_user_group_ids'] = [-1];
        }
        else
        {
            $input['allowed_user_group_ids'] = $this->filter('usable_user_group_ids', 'array-uint');
        }

        $form->basicEntitySave($warningCategory, $input);

        $phraseInput = $this->filter(
            [
                'title' => 'str'
            ]);
        $form->validate(function (FormAction $form) use ($phraseInput) {
            if ($phraseInput['title'] === '')
            {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });
        $form->apply(function () use ($phraseInput, $warningCategory) {
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
            $warningCategory = Helper::createEntity(WarningCategory::class);
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
        return Helper::plugin($this,\SV\WarningImprovements\XF\ControllerPlugin\WarningCategoryTree::class);
    }

    /**
     * @return \SV\WarningImprovements\Repository\WarningCategory|\XF\Mvc\Entity\Repository
     */
    protected function getCategoryRepo()
    {
        return Helper::repository(\SV\WarningImprovements\Repository\WarningCategory::class);
    }

    /**
     * @param      $id
     * @param null $with
     * @param null $phraseKey
     * @return WarningCategory|\XF\Mvc\Entity\Entity
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCategoryExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('SV\WarningImprovements:WarningCategory', $id, $with, $phraseKey);
    }


    /**
     * @param int               $id
     * @param string[]          $with
     * @param string|\XF\Phrase $phraseKey
     * @return WarningDefault|\XF\Mvc\Entity\Entity
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertDefaultExists(int $id, array $with = [], $phraseKey = null)
    {
        return $this->assertRecordExists('SV\WarningImprovements:WarningDefault', $id, $with, $phraseKey);
    }

    /**
     * @return \SV\WarningImprovements\XF\Entity\WarningDefinition
     */
    private function getCustomWarningDefinition()
    {
        /** @var \SV\WarningImprovements\XF\Repository\Warning $warningRepo */
        $warningRepo = $this->getWarningRepo();

        return $warningRepo->getCustomWarningDefinition();
    }
}