<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\Entity;

use XF\Entity\AbstractCategoryTree;
use XF\Entity\User;
use XF\Entity\WarningDefinition;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null warning_category_id
 * @property int warning_count
 * @property array allowed_user_group_ids
 * @property int|null parent_category_id
 * @property int display_order
 * @property int lft
 * @property int rgt
 * @property int depth
 * @property array breadcrumb_data
 *
 * GETTERS
 * @property int|null category_id
 * @property bool is_usable
 * @property \XF\Phrase title
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \SV\WarningImprovements\Entity\WarningCategory Parent
 * @property \SV\WarningImprovements\Entity\WarningCategory[] ChildCategories
 * @property \XF\Entity\WarningDefinition[] WarningDefinitions
 * @property \XF\Entity\WarningAction[] WarningActions
 * @property \XF\Entity\PermissionCacheContent[] Permissions
 */
class WarningCategory extends AbstractCategoryTree
{
    /**
     * @return bool
     */
    public function getIsUsable()
    {
        if ($this->Parent && !$this->Parent->is_usable)
        {
            return false;
        }

        if (in_array(-1, $this->allowed_user_group_ids ))
        {
            return true;
        }

        return \XF::visitor()->isMemberOf($this->allowed_user_group_ids);
    }

    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase($this->getPhraseName('title'));
    }

    /**
     * @param string $type
     * @return string
     */
    public function getPhraseName($type)
    {
        return 'sv_warning_category_' . $type . '.' . $this->warning_category_id;
    }

    /**
     * @param string $type
     * @return \XF\Entity\Phrase
     */
    public function getMasterPhrase($type)
    {
        $relation = 'Master' . ucfirst($type);
        $phrase = $this->$relation;

        if (!$phrase)
        {
            /** @var \XF\Entity\Phrase $phrase */
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function () use ($type) { return $this->getPhraseName($type); });
            $phrase->language_id = 0; // 0 = master
        }

        return $phrase;
    }

    public function warningAdded(/** @noinspection PhpUnusedParameterInspection */
        WarningDefinition $warningDefinition)
    {
        $this->rebuildCounters();
    }

    public function warningRemoved(/** @noinspection PhpUnusedParameterInspection */
        WarningDefinition $warningDefinition)
    {
        $this->rebuildCounters();
    }

    protected function _postSave()
    {
        if ($this->isUpdate())
        {
            if ($this->isChanged('warning_category_id'))
            {
                foreach ($this->_structure->relations AS $name => $relation)
                {
                    if ($relation['entity'] == 'XF:Phrase')
                    {
                        /** @var \XF\Entity\Phrase $maserPhrase */
                        $maserPhrase = $this->getExistingRelation($name);

                        if ($maserPhrase)
                        {
                            $type = substr(strtolower($name), 6); // strip Master
                            $maserPhrase->title = $this->getPhraseName($type);
                            $maserPhrase->save();
                        }
                    }
                }
            }
        }

        if ($this->isChanged(['parent_category_id', 'display_order']))
        {
            $this->scheduleNestedSetRebuild();
        }
    }

    /**
     * @return int|null
     */
    protected function getCategoryId()
    {
        return $this->warning_category_id;
    }

    protected function _preDelete()
    {
        /** @var \SV\WarningImprovements\Repository\WarningCategory $warningCategoryRepo */
        $warningCategoryRepo = $this->repository('SV\WarningImprovements:WarningCategory');
        /** @var \SV\WarningImprovements\Finder\WarningCategory|\XF\Mvc\Entity\Finder $warningCategoryChildFinder */
        $warningCategoryChildFinder = $warningCategoryRepo->findChildren($this);
        $warningCategoryIds = $warningCategoryChildFinder->pluckFrom('warning_category_id')
                                                         ->fetch()
                                                         ->toArray();
        $warningCategoryIds[] = $this->warning_category_id;

        /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $customWarningDefinition */
        $customWarningDefinition = $this->finder('XF:WarningDefinition')
                                        ->where('warning_definition_id', '=', 0)
                                        ->where('sv_warning_category_id', '=', $warningCategoryIds)
                                        ->fetchOne();

        if ($customWarningDefinition)
        {
            /** @var \SV\WarningImprovements\Finder\WarningCategory|\XF\Mvc\Entity\Finder $warningCategoryFinder */
            $warningCategoryFinder = $this->finder('SV\WarningImprovements:WarningCategory');
            /** @var \SV\WarningImprovements\Entity\WarningCategory $newParentCategory */
            $newParentCategory = $warningCategoryFinder->where('warning_category_id', '<>', $warningCategoryIds)
                                                       ->fetch();

            if (!$newParentCategory)
            {
                $this->error(\XF::phrase('sv_warning_improvements_last_category_cannot_be_deleted'));

                return;
            }

            $customWarningDefinition->sv_warning_category_id = $newParentCategory->warning_category_id;
            $customWarningDefinition->save();
        }
    }

    protected function _postDelete()
    {
        foreach ($this->_structure->relations AS $name => $relation)
        {
            if ($relation['entity'] == 'XF:Phrase')
            {
                if ($this->$name) // $name is the name of Relation
                {
                    $this->$name->delete();
                }
            }
        }

        if ($this->getOption('delete_contents'))
        {
            $this->app()->jobManager()->enqueueUnique('sv_WarningImprovementsCategoryDelete' . $this->warning_category_id, 'SVW\WarningImprovements:CategoryDelete', [
                'warning_category_id' => $this->warning_category_id
            ]);
        }
    }

    protected function scheduleNestedSetRebuild()
    {
        $entityType = $this->structure()->shortName;
        \XF::runOnce('rebuildTree-' . $entityType, function()
        {
            /** @var \SV\WarningImprovements\Service\Warning\CategoryRebuildNestedSet $service */
            $service = $this->app()->service('SV\WarningImprovements:Warning\CategoryRebuildNestedSet');
            $service->rebuildNestedSetInfo();
        });
    }

    public function rebuildCounters()
    {
        $this->rebuildWarningCount();

        return true;
    }

    /**
     * @return int
     */
    public function rebuildWarningCount()
    {
        $warningCount = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_warning_definition
			WHERE sv_warning_category_id = ?
		", $this->warning_category_id);

        $this->warning_count = max(0, $warningCount);

        return $this->warning_count;
    }

    /**
     * @return array
     */
    public function getCategoryListExtras()
    {
        return [
            'warning_count' => $this->warning_count
        ];
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_sv_warning_category';
        $structure->shortName = 'SV\WarningImprovements:WarningCategory';
        $structure->primaryKey = 'warning_category_id';
        $structure->columns = [
            'warning_category_id'    => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'warning_count'          => ['type' => self::UINT, 'default' => 0],
            'allowed_user_group_ids' => [
                'type' => self::LIST_COMMA, 'default' => [User::GROUP_REG],
                'list' => ['type' => 'posint', 'unique' => true, 'sort' => SORT_NUMERIC]
            ]
        ];
        $structure->getters = [
            'category_id' => true,
            'is_usable' => true,
            'title'     => true
        ];
        $structure->relations = [
            'MasterTitle'        => [
                'entity'     => 'XF:Phrase',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0], // master
                    ['title', '=', 'sv_warning_category_title.', '$warning_category_id']
                ]
            ],
            'Parent'             => [
                'entity'     => 'SV\WarningImprovements:WarningCategory',
                'type'       => self::TO_ONE,
                'conditions' => [['warning_category_id', '=', '$parent_category_id']],
                'primary'    => true
            ],
            'ChildCategories'    => [
                'entity'     => 'SV\WarningImprovements:WarningCategory',
                'type'       => self::TO_MANY,
                'conditions' => [['parent_category_id', '=', '$warning_category_id']],
                'primary'    => true
            ],
            'WarningDefinitions' => [
                'entity'     => 'XF:WarningDefinition',
                'type'       => self::TO_MANY,
                'conditions' => [['sv_warning_category_id', '=', '$warning_category_id']],
                'primary'    => true
            ],
            'WarningActions'     => [
                'entity'     => 'XF:WarningAction',
                'type'       => self::TO_MANY,
                'conditions' => [['sv_warning_category_id', '=', '$warning_category_id']],
                'primary'    => true
            ]
        ];
        $structure->options = [
            'delete_contents' => true
        ];

        static::addCategoryTreeStructureElements($structure);

        $structure->columns['parent_category_id']['nullable'] = true;


        // The TreeStructured behavior's delete code, and re-sorting code isn't sanely extendable enough to match how
        // Warning Categories are structured,
        unset($structure->behaviors['XF:TreeStructured']); // say no to raw!

        return $structure;
    }
}
