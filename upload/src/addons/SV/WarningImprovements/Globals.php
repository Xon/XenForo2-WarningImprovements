<?php

namespace SV\WarningImprovements;

use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;

/**
 * Add-on globals.
 */
class Globals
{
    /** @var int|null */
    public static $profileUserId = null;

    /** @var null|ExtendedWarningEntity */
    public static $warningObj = null;

    /** @var array */
    public static $warningInput = [];

    /**
     * Private constructor, use statically.
     */
    private function __construct() { }
}
