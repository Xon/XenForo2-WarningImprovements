<?php

namespace SV\WarningImprovements\Entity;

interface SupportsWrappingContentWithSpoilerInterface
{
    public function isContentWrappedInSpoilerForSvWarnImprov() : bool;

    public function getContentSpoilerTitleForSvWarnImprov(): ?string;
}