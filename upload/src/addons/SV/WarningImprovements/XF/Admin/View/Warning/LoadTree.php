<?php

namespace SV\WarningImprovements\XF\Admin\View\Warning;

/**
 * Extends \XF\Admin\View\Warning\LoadTree
 */
class LoadTree extends XFCP_LoadTree
{
    public function renderJson()
    {
        return [
            'output' => $this->params['tree']
        ];
    }
}