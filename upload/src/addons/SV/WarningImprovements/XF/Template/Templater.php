<?php

namespace SV\WarningImprovements\XF\Template;

use SV\WarningImprovements\Entity\SupportsWrappingContentWithSpoilerInterface;

class Templater extends XFCP_Templater
{
    public function fnBbCode(
        $templater,
        &$escape,
        $bbCode,
        $context,
        $content,
        array $options = [],
        $type = 'html'
    )
    {
        $escape = false;

        if ($content instanceof SupportsWrappingContentWithSpoilerInterface
            && $content->isContentWrappedInSpoilerForSvWarnImprov())
        {
            $title = $content->getContentSpoilerTitleForSvWarnImprov() ?? '';

            $bbCode = "[SPOILER=\"{$title}\"]{$bbCode}[/SPOILER]";
        }

        return parent::fnBbCode(
            $templater,
            $escape,
            $bbCode,
            $context,
            $content,
            $options,
            $type
        );
    }
}