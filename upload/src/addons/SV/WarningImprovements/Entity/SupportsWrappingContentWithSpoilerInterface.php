<?php

namespace SV\WarningImprovements\Entity;

interface SupportsWrappingContentWithSpoilerInterface
{
    public function isContentWrappedInSpoilerForSvWarnImprov() : bool;

    /**
     * @return string|null
     */
    public function getContentSpoilerTitleForSvWarnImprov();
}