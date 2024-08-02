<?php

namespace SV\WarningImprovements\Entity;

use SV\StandardLib\Helper;
use SV\WarningImprovements\Finder\WarningCategory as WarningCategoryFinder;
use SV\WarningImprovements\Service\Warning\CategoryRebuildNestedSet as CategoryRebuildNestedSetService;
use SV\WarningImprovements\XF\Entity\WarningAction as ExtendedWarningActionEntity;
use XF\Entity\AbstractCategoryTree;
use SV\WarningImprovements\XF\Entity\WarningDefinition as ExtendedWarningDefinitionEntity;
use XF\Entity\PermissionCacheContent as PermissionCacheContentEntity;
use XF\Entity\Phrase as PhraseEntity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 *
 * @property int|null                            $warning_category_id
 * @property int                                                    $warning_count
 * @property array                                                  $allowed_user_group_ids
 * @property array                                                  $allowed_user_group_ids_
 * @property int|null                                               $parent_category_id
 * @property int                                                    $display_order
 * @property int                                                    $lft
 * @property int                                                    $rgt
 * @property int                                    $depth
 * @property array                                  $breadcrumb_data
 * GETTERS
 * @property int|null                               $category_id
 * @property bool                                   $is_usable
 * @property-read Phrase                            $title
 * RELATIONS
 * @property-read PhraseEntity                      $MasterTitle
 * @property-read WarningCategory                   $Parent
 * @property-read WarningCategory[]                 $ChildCategories
 * @property-read ExtendedWarningDefinitionEntity[] $WarningDefinitions
 * @property-read ExtendedWarningActionEntity[]     $WarningActions
 * @property-read PermissionCacheContentEntity[]    $Permissions
 */
class WarningCategory extends AbstractCategoryTree
{
    public function getAllowedUserGroupIds(): array
    {
        return \array_map('\intval', $this->allowed_user_group_ids_);
    }

    public function getIsUsable(): bool
    {
        if ($this->Parent && !$this->Parent->is_usable || !$this->allowed_user_group_ids)
        {
            return false;
        }

        if ($this->allowed_user_group_ids[0] === -1)
        {
            return true;
        }

        return \XF::visitor()->isMemberOf($this->allowed_user_group_ids);
    }

    public function getTitle(): Phrase
    {
        return \XF::phrase($this->getPhraseName('title'));
    }

    public function getPhraseName(string $type): string
    {
        return 'sv_warning_category_' . $type . '.' . $this->warning_category_id;
    }

    public function getMasterPhrase(string $type): PhraseEntity
    {
        $relation = 'Master' . ucfirst($type);
        $phrase = $this->getRelation($relation);

        if (!$phrase)
        {
            $phrase = Helper::createEntity(PhraseEntity::class);
            $phrase->title = $this->_getDeferredValue(function () use ($type) { return $this->getPhraseName($type); });
            $phrase->language_id = 0; // 0 = master
        }

        return $phrase;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function warningAdded(ExtendedWarningDefinitionEntity $warningDefinition)
    {
        $this->rebuildCounters();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function warningRemoved(ExtendedWarningDefinitionEntity $warningDefinition)
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
                    if ($relation['entity'] === 'XF:Phrase')
                    {
                        /** @var PhraseEntity $maserPhrase */
                        $maserPhrase = $this->getExistingRelation($name);

                        if ($maserPhrase)
                        {
                            $type = \substr(\strtolower($name), 6); // strip Master
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
        $warningCategoryCount = Helper::finder(WarningCategoryFinder::class)
                                      ->where('warning_category_id', '!=', $this->warning_category_id)
                                      ->total();
        if (!$warningCategoryCount)
        {
            $this->error(\XF::phrase('sv_warning_improvements_last_category_cannot_be_deleted'));

            return;
        }

        if ($this->getOption('delete_contents') && $this->warning_category_id)
        {
            foreach ($this->WarningDefinitions as $warning)
            {
                if (!$warning->is_custom)
                {
                    $warning->preDelete();
                }
            }
            foreach ($this->ChildCategories as $childCategory)
            {
                $childCategory->setOption('delete_contents', true);
                $childCategory->preDelete();
            }
            foreach ($this->WarningActions as $action)
            {
                $action->preDelete();
            }
        }
    }

    protected function _postDelete()
    {
        foreach ($this->_structure->relations AS $name => $relation)
        {
            if ($relation['entity'] === 'XF:Phrase')
            {
                $relation = $this->getRelation($name);
                if ($relation !== null)
                {
                    $relation->delete();
                }
            }
        }

        if (!$this->warning_category_id)
        {
            return;
        }

        $parentCategoryId = $this->parent_category_id;

        if ($this->getOption('delete_contents'))
        {
            foreach ($this->WarningDefinitions as $warning)
            {
                if ($warning->is_custom)
                {
                    $warning->sv_warning_category_id = $parentCategoryId;
                    $warning->save(true, false);
                }
                else
                {
                    $warning->delete(true, false);
                }
            }
            foreach ($this->ChildCategories as $childCategory)
            {
                $childCategory->delete(true, false);
            }
            foreach ($this->WarningActions as $action)
            {
                $action->delete(true, false);
            }
        }
        else
        {
            foreach ($this->WarningDefinitions as $warning)
            {
                $warning->sv_warning_category_id = $parentCategoryId;
                $warning->save(true, false);
            }
            foreach ($this->ChildCategories as $childCategory)
            {
                $childCategory->parent_category_id = $parentCategoryId;
                $childCategory->save(true, false);
            }
            foreach ($this->WarningActions as $action)
            {
                $action->sv_warning_category_id = $parentCategoryId;
                $action->save(true, false);
            }
        }
        // xf_warning_action_trigger will refer to invalid warning categories
        // WarningPointsChange will dump into global category/actions as there isn't much else that can be done
    }

    protected function scheduleNestedSetRebuild()
    {
        $entityType = $this->structure()->shortName;
        \XF::runOnce('rebuildTree-' . $entityType, function()
        {
            $service = Helper::service(CategoryRebuildNestedSetService::class);
            $service->rebuildNestedSetInfo();
        });
    }

    public function rebuildCounters()
    {
        $this->rebuildWarningCount();
    }

    public function rebuildWarningCount()
    {
        $warningCount = \XF::db()->fetchOne('
			SELECT COUNT(*)
			FROM xf_warning_definition
			WHERE sv_warning_category_id = ?
		', $this->warning_category_id);

        $this->warning_count = \max(0, $warningCount);
    }

    public function getCategoryListExtras(): array
    {
        return [
            'warning_count' => $this->warning_count,
        ];
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_warning_category';
        $structure->shortName = 'SV\WarningImprovements:WarningCategory';
        $structure->primaryKey = 'warning_category_id';
        $structure->columns = [
            'warning_category_id'    => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'warning_count'          => ['type' => self::UINT, 'default' => 0],
            'allowed_user_group_ids' => [
                'type' => self::LIST_COMMA, 'default' => [-1],
                'list' => ['type' => 'int', 'unique' => true, 'sort' => SORT_NUMERIC],
            ],
        ];
        $structure->getters = [
            'category_id'            => true, // used in sorting?
            'is_usable'              => ['getter' => 'getIsUsable', 'cache' => true],
            'title'                  => ['getter' => 'getTitle', 'cache' => true],
            'allowed_user_group_ids' => ['getter' => 'getAllowedUserGroupIds', 'cache' => true],
        ];
        $structure->relations = [
            'MasterTitle'        => [
                'entity'     => 'XF:Phrase',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0], // master
                    ['title', '=', 'sv_warning_category_title.', '$warning_category_id'],
                ],
            ],
            'Parent'             => [
                'entity'     => 'SV\WarningImprovements:WarningCategory',
                'type'       => self::TO_ONE,
                'conditions' => [['warning_category_id', '=', '$parent_category_id']],
                'primary'    => true,
            ],
            'ChildCategories'    => [
                'entity'     => 'SV\WarningImprovements:WarningCategory',
                'type'       => self::TO_MANY,
                'conditions' => [['parent_category_id', '=', '$warning_category_id']],
                'primary'    => true,
            ],
            'WarningDefinitions' => [
                'entity'     => 'XF:WarningDefinition',
                'type'       => self::TO_MANY,
                'conditions' => [['sv_warning_category_id', '=', '$warning_category_id']],
                'primary'    => true,
            ],
            'WarningActions'     => [
                'entity'     => 'XF:WarningAction',
                'type'       => self::TO_MANY,
                'conditions' => [['sv_warning_category_id', '=', '$warning_category_id']],
                'primary'    => true,
            ],
        ];
        $structure->options = [
            'delete_contents' => true,
        ];

        static::addCategoryTreeStructureElements($structure);

        $structure->columns['parent_category_id']['nullable'] = true;


        // The TreeStructured behavior's delete code, and re-sorting code isn't sanely extendable enough to match how
        // Warning Categories are structured,
        unset($structure->behaviors['XF:TreeStructured']); // say no to raw!

        return $structure;
    }
}
