<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\WarningImprovements\Entity;

trait SupportsWrappingContentWithSpoilerTrait
{
    public function isContentWrappedInSpoilerForSvWarnImprov() : bool
    {
        if (!$this instanceof SupportsEmbedMetadataInterface)
        {
            return false;
        }

        return isset($this->embed_metadata['sv_spoiler_contents']);
    }

    /**
     * @inheritDoc
     */
    public function getContentSpoilerTitleForSvWarnImprov()
    {
        if (!$this->isContentWrappedInSpoilerForSvWarnImprov())
        {
            return null;
        }

        return $this->embed_metadata['sv_content_spoiler_title'];
    }
}