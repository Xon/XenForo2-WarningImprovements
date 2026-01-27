<?php

namespace SV\WarningImprovements;

use SV\WarningImprovements\XF\Entity\Warning as ExtendedWarningEntity;
use XF\Entity\User as UserEntity;
use function is_callable;

/**
 * Add-on globals.
 */
abstract class Globals
{
    /** @var int|null */
    public static $profileUserId = null;

    /** @var null|ExtendedWarningEntity */
    public static $warningObj = null;

    /** @var array */
    public static $warningInput = [];

    /**
     * XF2.2.0/XF2.2.1 compatibility shim
     * @template T
     * @param UserEntity $user
     * @param callable(): T $callable
     * @return T
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function asVisitorWithLang(UserEntity $user, callable $callable)
    {
        if (\XF::$versionId >= 2020270)
        {
            return \XF::asVisitor($user, $callable, true);
        }

        $oldVisitor = \XF::visitor();
        \XF::setVisitor($user);

        $oldLang = \XF::language();
        // Compatibility for XF2.1 & XF2.2
        $app = \XF::app();
        $newLang = $app->language($user->language_id);
        if (is_callable([$newLang, 'isUsable']))
        {
            if (!$newLang->isUsable($user))
            {
                $newLang = $app->language();
            }
        }
        else
        {
            if (!($newLang->user_selectable ?? false) && !$user->is_admin)
            {
                $newLang = $app->language();
            }
        }
        $newLangeOrigTz = $newLang->getTimeZone();
        $newLang->setTimeZone($user->timezone);
        \XF::setLanguage($newLang);

        try
        {
            return $callable();
        }
        finally
        {
            \XF::setVisitor($oldVisitor);

            $newLang->setTimeZone($newLangeOrigTz);
            \XF::setLanguage($oldLang);
        }
    }

    /**
     * Private constructor, use statically.
     */
    private function __construct() { }
}
