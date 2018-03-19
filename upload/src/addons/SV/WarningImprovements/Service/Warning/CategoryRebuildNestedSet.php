<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\Service\Warning;

use XF\App;
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

    protected function getEntities()
    {
        return $this->finder('SV\WarningImprovements:WarningCategory')
                    ->order('display_order')
                    ->fetch();
    }

    public function rebuildNestedSetInfo()
    {
        $this->setupTree();

        $this->db()->beginTransaction();
        $this->_rebuildNestedSetInfo(0);
        $this->db()->commit();
    }

    protected function _rebuildNestedSetInfo($id, $depth = -1, &$counter = 0)
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