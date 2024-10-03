<?php

namespace SV\WarningImprovements\Entity;

trait SupportsWrappingContentWithSpoilerTrait
{
    public function isContentWrappedInSpoilerForSvWarnImprov() : bool
    {
        /** @noinspection PhpInstanceofIsAlwaysTrueInspection */
        if (!($this instanceof SupportsEmbedMetadataInterface))
        {
            return false;
        }

        // catch cases where the warning has been deleted, but embed_metadata not cleaned up
        $warningId = $this->warning_id ?? 0;
        if ($warningId === 0)
        {
            return false;
        }

        return isset($this->embed_metadata['sv_spoiler_contents']);
    }

    public function getContentSpoilerTitleForSvWarnImprov(): ?string
    {
        if (!$this->isContentWrappedInSpoilerForSvWarnImprov())
        {
            return null;
        }

        return $this->embed_metadata['sv_content_spoiler_title'];
    }
}