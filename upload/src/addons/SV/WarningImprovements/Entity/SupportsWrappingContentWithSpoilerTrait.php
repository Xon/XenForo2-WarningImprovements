<?php

namespace SV\WarningImprovements\Entity;

trait SupportsWrappingContentWithSpoilerTrait
{
    public function isContentWrappedInSpoilerForSvWarnImprov(): bool
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

        $embedMetadata = $this->embed_metadata;

        return (bool)($embedMetadata['sv_spoiler_contents'] ?? false);
    }

    public function getContentSpoilerTitleForSvWarnImprov(): ?string
    {
        if (!$this->isContentWrappedInSpoilerForSvWarnImprov())
        {
            return null;
        }

        $embedMetadata = $this->embed_metadata;

        return $embedMetadata['sv_content_spoiler_title'] ?? null;
    }
}