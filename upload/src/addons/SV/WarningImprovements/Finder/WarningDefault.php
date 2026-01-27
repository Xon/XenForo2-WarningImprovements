<?php

namespace SV\WarningImprovements\Finder;

use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection as AbstractCollection;
use XF\Mvc\Entity\Finder as Finder;
use SV\WarningImprovements\Entity\WarningDefault as WarningDefaultEntity;

/**
 * @method AbstractCollection<WarningDefaultEntity>|WarningDefaultEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method WarningDefaultEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,WarningDefaultEntity>
 * @extends Finder<WarningDefaultEntity>
 */
class WarningDefault extends Finder
{
    /**
     * @return static
     */
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }
}
