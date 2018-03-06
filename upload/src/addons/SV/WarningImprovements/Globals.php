<?php

/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SV\WarningImprovements;

/**
 * Add-on globals.
 */
class Globals
{
    /** @var int */
    public static $customWarningPhrase_version_id = 101011;

    /** @var string */
    public static $customWarningPhrase_version_string = '1.1.1s';

    /** @var int|null */
    public static $profileUserId = null;

    /** @var \SV\WarningImprovements\XF\Entity\Warning */
    public static $warningObj = null;

    /** @var array[] */
    public static $warningInput = null;

    /**
     * Private constructor, use statically.
     */
    private function __construct() { }
}
