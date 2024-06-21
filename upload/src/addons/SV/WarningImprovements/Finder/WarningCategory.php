<?php

namespace SV\WarningImprovements\Finder;

use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection as AbstractCollection;
use XF\Mvc\Entity\Finder as Finder;
use SV\WarningImprovements\Entity\WarningCategory as WarningCategoryEntity;

 /**
 * @method AbstractCollection<WarningCategoryEntity>|WarningCategoryEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method WarningCategoryEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,WarningCategoryEntity>
 * @extends Finder<WarningCategoryEntity>
 */
class WarningCategory extends Finder
{
    /**
      * @return static
      */
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }
}
