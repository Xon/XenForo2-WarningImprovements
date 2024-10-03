<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\WarningImprovements\Entity;

use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use XF\Mvc\Entity\Structure;
use function array_key_exists;

trait SupportsEmbedMetadataTrait
{
    protected function _preSave()
    {
        if ($this->getOption('svCopyWarningEmbedData') && $this->isChanged('embed_metadata'))
        {
            $fields = ['sv_spoiler_contents', 'sv_content_spoiler_title', 'sv_disable_reactions'];

            // XF doesn't preserve the contents of embed_metadata across edits, so grab the flags/title out of previous
            // values and store in the new version of embed_metadata
            // or extract fields from the warning, which will be the canonical versions
            $metadata = $this->embed_metadata;
            if (array_key_exists('Warning', $this->structure()->relations))
            {
                $warning = $this->getRelation('Warning');
                if ($warning instanceof ExtendedWarningEntity)
                {
                    foreach ($fields as $key)
                    {
                        $value = $warning->get($key);
                        if ($value === null || $value === false)
                        {
                            continue;
                        }

                        $metadata[$key] = $value;
                    }
                }
            }
            else
            {
                $oldMetaData = $this->getPreviousValue('embed_metadata');
                foreach ($fields as $key)
                {
                    if (!isset($metadata[$key]) && isset($oldMetaData[$key]))
                    {
                        $metadata[$key] = $oldMetaData[$key];
                    }
                }
            }
            $this->embed_metadata = $metadata;
        }

        parent::_preSave();
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->options['svWarnImprov'] = true;
        $structure->options['svCopyWarningEmbedData'] = true;

        return $structure;
    }
}