<?php

namespace SV\WarningImprovements\XF\ControllerPlugin;

use SV\WarningImprovements\Entity\SupportsDisablingReactionInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Message as MessageReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ExceptionReply;

class Reaction extends XFCP_Reaction
{
    /**
     * @param Entity $content
     * @param string $link
     * @param string|null $title
     * @param array $breadcrumbs
     * @param array $linkParams
     *
     * @return AbstractReply|MessageReply|ViewReply
     *
     * @throws ExceptionReply
     */
    public function actionReactions(
        Entity $content,
        $link,
        $title = null,
        array $breadcrumbs = [],
        array $linkParams = []
    )
    {
        if ($content instanceof SupportsDisablingReactionInterface
            && $content->hasDisabledReactionsListForSvWarnImprov($content))
        {
            throw $this->exception($this->noPermission());
        }

        return parent::actionReactions($content, $link, $title, $breadcrumbs, $linkParams);
    }
}