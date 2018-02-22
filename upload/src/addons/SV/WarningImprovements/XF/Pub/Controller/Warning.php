<?php

namespace SV\WarningImprovements\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Warning
 */
class Warning extends XFCP_Warning
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);

        return $response;
    }
}