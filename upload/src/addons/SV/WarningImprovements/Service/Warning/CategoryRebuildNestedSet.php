<?php

namespace SV\WarningImprovements\Service\Warning;

use XF\App;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Service\AbstractService;
use XF\Tree;

class CategoryRebuildNestedSet extends AbstractService
{
    /** @var Tree */
    protected $tree;

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    protected function setupTree()
    {
        if ($this->tree)
        {
            return;
        }

        $entities = $this->getEntities();
        $this->tree = new Tree($entities, 'parent_category_id', 0);
    }

    protected function getEntities(): AbstractCollection
    {
        return \SV\StandardLib\Helper::finder(\SV\WarningImprovements\Finder\WarningCategory::class)
                    ->order('display_order')
                    ->fetch();
    }

    public function rebuildNestedSetInfo()
    {
        $this->setupTree();

        \XF::db()->beginTransaction();
        $this->_rebuildNestedSetInfo(0);
        \XF::db()->commit();
    }

    protected function _rebuildNestedSetInfo(int $id, int $depth = -1, int &$counter = 0)
    {
        /** @var Entity $entity */
        $entity = $this->tree->getData($id);

        if ($entity)
        {
            $counter++;
        }
        $left = $counter;


        foreach ($this->tree->childIds($id) AS $childId)
        {
            $this->_rebuildNestedSetInfo($childId, $depth + 1, $counter);
        }

        if ($entity)
        {
            $counter++;
        }
        $right = $counter;

        if ($entity)
        {
            $updateData = [
                'lft'   => $left,
                'rgt'   => $right,
                'depth' => $depth
            ];

            $entity->fastUpdate($updateData);
        }
    }
}