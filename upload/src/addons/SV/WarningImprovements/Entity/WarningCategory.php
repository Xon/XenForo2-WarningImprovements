<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\Entity;

use SV\WarningImprovements\XF\Entity\WarningDefinition;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null warning_category_id
 * @property int|null parent_warning_category_id
 * @property int display_order
 * @property array allowed_user_group_ids
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \SV\WarningImprovements\Entity\WarningDefault Parent
 * @property \SV\WarningImprovements\Entity\WarningDefault[] ChildCategories
 * @property \XF\Entity\WarningDefinition[] WarningDefinitions
 * @property \XF\Entity\WarningAction[] WarningActions
 */
class WarningCategory extends Entity
{
    public function verifyParentWarningCategoryId($parentWarningCategoryId)
    {
        if ($parentWarningCategoryId === 0)
        {
            return true;
        }

        /** @var \SV\WarningImprovements\Finder\WarningCategory $finder */
        $finder = $this->finder('SV\WarningImprovements:WarningCategory');
        $categoriesInWarningCategories = $finder->inParentWarningCategory($this)->fetch();

        if ($this->isInsert() || empty($categoriesInWarningCategories))
        {
            /** @var \SV\WarningImprovements\Entity\WarningCategory $parentWarningCategory */
            $parentWarningCategory = $this->_em->findOne('SV\WarningImprovements:WarningCategory', $parentWarningCategoryId);
            if ($parentWarningCategory && $parentWarningCategory->parent_warning_category_id === 0)
            {
                return true;
            }
        }

        $this->error('sv_please_enter_valid_warning_category_id', 'parent_warning_category_id');
        return false;
    }

    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase($this->getPhraseName('title'));
    }

    public function getPhraseName($type)
    {
        return 'sv_warning_category_' . $type . '.' . $this->warning_category_id;
    }

    /**
     * @param $type
     *
     * @return \XF\Entity\Phrase
     */
    public function getMasterPhrase($type)
    {
        $relation = 'Master' . ucfirst($type);
        $phrase = $this->$relation;

        if (!$phrase)
        {
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function() use ($type) { return $this->getPhraseName($type); });
            $phrase->language_id = 0; // 0 = master
        }

        return $phrase;
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

        foreach ($this->ChildCategories AS $childCategory)
        {
            $childCategory->delete();
        }

        foreach ($this->WarningDefinitions AS $warningDefinition)
        {
            /** @var \SV\WarningImprovements\XF\Entity\WarningDefinition $warningDefinition */
            if ($warningDefinition->warning_definition_id !== 0)
            {
                $warningDefinition->delete();
            }
            else
            {
                /** @var \SV\WarningImprovements\Entity\WarningCategory $firstWarningCategory */
                $firstWarningCategory = $this->finder('SV\WarningImprovements:WarningCategory')
                    ->order(['parent_warning_category_id', 'display_order'])
                    ->fetch()
                    ->first();
                $warningDefinition->sv_warning_category_id = $firstWarningCategory->warning_category_id;
                $warningDefinition->save();
            }
        }

        foreach ($this->WarningActions AS $warningAction)
        {
            $warningAction->delete();
        }
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
            'warning_category_id'        => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'parent_warning_category_id' => ['type' => self::UINT, 'nullable' => true],
            'display_order'              => ['type' => self::UINT, 'default' => 0],
            'allowed_user_group_ids'     => [
                'type' => self::LIST_COMMA, 'default' => [\XF\Entity\User::GROUP_REG],
                'list' => ['type' => 'posint', 'unique' => true, 'sort' => SORT_NUMERIC]
            ],

        ];
        $structure->getters = [];
        $structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0], // master
                    ['title', '=', '']
                ]
            ],
            'Parent' => [
                'entity' => 'SV\WarningImprovements:WarningDefault',
                'type' => self::TO_ONE,
                'conditions' => [['warning_category_id', '=', '$parent_warning_category_id']],
                'primary' => true
            ],
            'ChildCategories' => [
                'entity' => 'SV\WarningImprovements:WarningDefault',
                'type' => self::TO_MANY,
                'conditions' => [['parent_warning_category_id', '=', '$warning_category_id']],
                'primary' => true
            ],
            'WarningDefinitions' => [
                'entity' => 'XF:WarningDefinition',
                'type' => self::TO_MANY,
                'conditions' => [['sv_warning_category_id', '=', '$warning_category_id']],
                'primary' => true
            ],
            'WarningActions' => [
                'entity' => 'XF:WarningAction',
                'type' => self::TO_MANY,
                'conditions' => [['sv_warning_category_id', '=', '$warning_category_id']],
                'primary' => true
            ]
        ];

        return $structure;
    }
}
