<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements\XF\ControllerPlugin;

use XF\ControllerPlugin\AbstractCategoryTree;
use XF\Mvc\ParameterBag;

class WarningCategoryTree extends AbstractCategoryTree
{
    protected $viewFormatter     = 'SV\WarningImprovements\XF:WarningCategory\%s';
    protected $templateFormatter = 'sv_warning_category_%s';
    protected $routePrefix       = 'warnings';
    protected $entityIdentifier  = 'SV\WarningImprovements:WarningCategory';
    protected $primaryKey        = 'warning_category_id';

    public function actionDelete(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($this->filter($this->primaryKey, 'uint'));
        if ($this->isPost())
        {
            $childAction = $this->filter('child_nodes_action', 'str');
            $category->setOption('delete_contents', $childAction);

            $category->delete();

            return $this->redirect($this->buildLink($this->routePrefix));
        }
        else
        {
            $viewParams = [
                'category' => $category
            ];

            return $this->view(
                $this->formatView('Delete'),
                $this->formatTemplate('delete'),
                $viewParams
            );
        }
    }
}