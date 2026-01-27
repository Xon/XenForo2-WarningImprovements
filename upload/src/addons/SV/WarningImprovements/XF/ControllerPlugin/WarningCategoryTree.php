<?php

namespace SV\WarningImprovements\XF\ControllerPlugin;

use XF\ControllerPlugin\AbstractCategoryTree;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class WarningCategoryTree extends AbstractCategoryTree
{
    protected $viewFormatter     = 'SV\WarningImprovements\XF:WarningCategory\%s';
    protected $templateFormatter = 'sv_warning_category_%s';
    protected $routePrefix       = 'warnings';
    protected $entityIdentifier  = 'SV\WarningImprovements:WarningCategory';
    protected $primaryKey        = 'warning_category_id';

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function actionDelete(ParameterBag $params): AbstractReply
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
                'category' => $category,
            ];

            return $this->view(
                $this->formatView('Delete'),
                $this->formatTemplate('delete'),
                $viewParams
            );
        }
    }
}