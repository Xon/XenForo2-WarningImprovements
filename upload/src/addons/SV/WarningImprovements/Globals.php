<?php

namespace SV\WarningImprovements;

/**
 * Add-on globals.
 */
class Globals
{
    /** @var int|null */
    public static $profileUserId = null;

    /** @var \SV\WarningImprovements\XF\Entity\Warning */
    public static $warningObj = null;

    /** @var array */
    public static $warningInput = [];

    /**
     * Private constructor, use statically.
     */
    private function __construct() { }
}
