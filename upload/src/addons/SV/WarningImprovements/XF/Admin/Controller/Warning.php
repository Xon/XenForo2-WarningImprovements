<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpUnusedParameterInspection
 */

namespace SV\WarningImprovements\XF\Admin\Controller;

use SV\StandardLib\Helper;
use SV\WarningImprovements\Entity\WarningCategory as WarningCategoryEntity;
use SV\WarningImprovements\Entity\WarningDefault as WarningDefaultEntity;
use SV\WarningImprovements\Finder\WarningDefault as WarningDefaultFinder;
use SV\WarningImprovements\Repository\WarningCategory as WarningCategoryRepo;
use SV\WarningImprovements\Setup;
use SV\WarningImprovements\XF\ControllerPlugin\WarningCategoryTree as WarningCategoryTreePlugin;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use SV\WarningImprovements\XF\Repository\Warning as ExtendedWarningRepo;
use XF\AddOn\AddOn;
use XF\ControllerPlugin\Sort as SortPlugin;
use XF\Entity\Phrase as PhraseEntity;
use XF\Entity\WarningAction as WarningActionEntity;
use XF\Entity\WarningDefinition as WarningDefinitionEntity;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;
use XF\Mvc\Reply\View as ViewReply;
use XF\Repository\Node as NodeRepo;

/**
 * @extends \XF\Admin\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    public function actionIndex(ParameterBag $params)
    {
        if (!$this->isPost())
        {
            try
            {
                $addOn = new AddOn('SV\WarningImprovements', \XF::app()->addOnManager());

                $setup = new Setup($addOn, \XF::app());
                $setup->addDefaultPhrases();
                $setup->cleanupWarningCategories();
            }
            catch(\Exception $e)
            {
                // swallow exceptions
            }
        }

        $response = parent::actionIndex($params);

        if ($response instanceof ViewReply)
        {
            $categoryRepo = $this->getCategoryRepo();
            $categories = $categoryRepo->findCategoryList()->fetch();
            $categoryTree = $categoryRepo->createCategoryTree($categories);

            /** @var ExtendedWarningRepo $warningRepo */
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

            $escalatingDefaults = Helper::finder(WarningDefaultFinder::class)->fetch();

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

    public function warningAddEdit(WarningDefinitionEntity $warning)
    {
        $response = parent::warningAddEdit($warning);

        if ($response instanceof ViewReply)
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

    protected function warningSaveProcess(WarningDefinitionEntity $warning)
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
                /** @var PhraseEntity $masterContentSpoilerTitle */
                $masterContentSpoilerTitle = $warning->getRelationOrDefault('SvMasterContentSpoilerTitle', false);
                $masterContentSpoilerTitle->addon_id = '';
                $masterContentSpoilerTitle->phrase_text = $phraseInput['sv_content_spoiler_title'];
                $masterContentSpoilerTitle->save();
            }
        });

        return $formAction;
    }

    public function actionSort(): AbstractReply
    {
        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = $this->getWarningRepo();
        $warnings = $warningRepo->findWarningDefinitionsForListGroupedByCategory();

        if ($this->isPost())
        {
            $sorter = Helper::plugin($this, SortPlugin::class);

            $categoryRepo = $this->getCategoryRepo();
            $categories = $categoryRepo->findCategoryList();

            /** @var WarningCategoryEntity $category */
            foreach ($categories as $category)
            {
                $sortTree = $sorter->buildSortTree($this->filter('category-' . $category->warning_category_id, 'json-array'));

                $sortedTreeData = $sortTree->getAllData();
                $lastOrder = 0;

                foreach ($sortedTreeData as $warningId => $data)
                {
                    $lastOrder += 5;
                    /** @var ExtendedWarningDefinitionEntity $entry */
                    $entry = Helper::finder(WarningDefinitionEntity::class)
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

    public function _actionAddEdit(WarningActionEntity $action)
    {
        $response = parent::_actionAddEdit($action);

        if ($response instanceof ViewReply)
        {
            $nodeRepo = Helper::repository(NodeRepo::class);
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
    protected function _actionSaveProcess(WarningActionEntity $action)
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

    public function defaultActionAddEdit(WarningDefaultEntity $defaultAction)
    {
        $nodeRepo = Helper::repository(NodeRepo::class);
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

    public function actionDefaultEdit(ParameterBag $params): AbstractReply
    {
        $defaultAction = $this->assertDefaultExists($this->filter('warning_default_id', 'uint'));

        return $this->defaultActionAddEdit($defaultAction);
    }

    public function actionDefaultAdd(): AbstractReply
    {
        $defaultAction = Helper::createEntity(WarningDefaultEntity::class);

        return $this->defaultActionAddEdit($defaultAction);
    }

    protected function defaultSaveProcess(WarningDefaultEntity $defaultAction)
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

    public function actionDefaultSave(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();

        if ($warningDefaultId = $this->filter('warning_default_id', 'uint'))
        {
            $defaultAction = $this->assertDefaultExists($warningDefaultId);
        }
        else
        {
            $defaultAction = Helper::createEntity(WarningDefaultEntity::class);
        }

        $this->defaultSaveProcess($defaultAction)->run();

        return $this->redirect(
            $this->buildLink('warnings') . $this->buildLinkHash('warning_default-' . $defaultAction->getEntityId())
        );
    }

    public function actionDefaultDelete(ParameterBag $params): AbstractReply
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

    public function warningCategoryAddEdit(WarningCategoryEntity $warningCategory)
    {
        $categoryRepo = $this->getCategoryRepo();
        $categoryTree = $categoryRepo->createCategoryTree();

        $viewParams = [
            'category'     => $warningCategory,
            'categoryTree' => $categoryTree,
        ];

        return $this->view('XF:Warning\Category\Edit', 'sv_warning_category_edit', $viewParams);
    }

    public function actionCategoryAdd(): AbstractReply
    {
        $warningCategory = Helper::createEntity(WarningCategoryEntity::class);

        if ($parentCategoryId = $this->filter('parent_category_id', 'uint'))
        {
            $warningCategory->parent_category_id = $parentCategoryId;
        }

        return $this->warningCategoryAddEdit($warningCategory);
    }

    public function actionCategoryEdit(ParameterBag $params): AbstractReply
    {
        $warningCategory = $this->assertCategoryExists($this->filter('warning_category_id', 'uint'));

        return $this->warningCategoryAddEdit($warningCategory);
    }

    protected function categorySaveProcess(WarningCategoryEntity $warningCategory)
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

    public function actionCategorySave(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();

        $warningCategoryId = $this->filter('warning_category_id', 'uint');

        if ($warningCategoryId)
        {
            $warningCategory = $this->assertCategoryExists($warningCategoryId);
        }
        else
        {
            $warningCategory = Helper::createEntity(WarningCategoryEntity::class);
        }

        $this->categorySaveProcess($warningCategory)->run();

        return $this->redirect(
            $this->buildLink('warnings') . $this->buildLinkHash('warning_default-' . $warningCategory->getEntityId())
        );
    }

    public function actionCategoryDelete(ParameterBag $params): AbstractReply
    {
        return $this->getCategoryTreePlugin()->actionDelete($params);
    }

    public function actionCategorySort(): AbstractReply
    {
        return $this->getCategoryTreePlugin()->actionSort();
    }

    protected function getCategoryTreePlugin(): WarningCategoryTreePlugin
    {
        return Helper::plugin($this, WarningCategoryTreePlugin::class);
    }

    protected function getCategoryRepo(): WarningCategoryRepo
    {
        return Helper::repository(WarningCategoryRepo::class);
    }

    /**
     * @param int|null $id
     * @param array    $with
     * @param null     $phraseKey
     * @return WarningCategoryEntity
     * @throws ReplyException
     */
    protected function assertCategoryExists(?int $id, array $with = [], $phraseKey = null): WarningCategoryEntity
    {
        return $this->assertRecordExists('SV\WarningImprovements:WarningCategory', $id, $with, $phraseKey);
    }

    /**
     * @param int      $id
     * @param string[] $with
     * @param null     $phraseKey
     * @return WarningDefaultEntity
     * @throws ReplyException
     */
    protected function assertDefaultExists(int $id, array $with = [], $phraseKey = null): WarningDefaultEntity
    {
        return $this->assertRecordExists('SV\WarningImprovements:WarningDefault', $id, $with, $phraseKey);
    }

    private function getCustomWarningDefinition(): ExtendedWarningDefinitionEntity
    {
        /** @var ExtendedWarningRepo $warningRepo */
        $warningRepo = $this->getWarningRepo();

        return $warningRepo->getCustomWarningDefinition();
    }
}