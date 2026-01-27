<?php

namespace SV\WarningImprovements;

use XF\AddOn\AddOn;
use XF\Entity\AddOn as AddOnEntity;

abstract class InstallerListener
{
    /**
     * Called when the post-rebuild code for an add-on has been run.
     *
     * Event hint: The add-on ID for the add-on being rebuilt.
     *
     * @param AddOn       $addOn          The AddOn object for the add-on being rebuilt.
     * @param AddOnEntity $installedAddOn The add-on entity.
     * @param array       $json           An array decoded from the add-on's addon.json file.
     */
    public static function addonPostRebuild(
        AddOn $addOn,
        AddOnEntity $installedAddOn,
        array $json
    )
    {
        static::applyAddOnPostInstallation($addOn, $installedAddOn, $json);
    }

    /**
     * Called when the post-install code for an add-on has been run.
     *
     * Event hint: The add-on ID for the add-on being installed.
     *
     * @param AddOn       $addOn          The AddOn object for the add-on being installed.
     * @param AddOnEntity $installedAddOn The newly created add-on entity.
     * @param array       $json           An array decoded from the add-on's addon.json file.
     * @param array       $stateChanges   An array for storing state changes such as post-install controller redirects.
     */
    public static function addonPostInstall(
        AddOn $addOn,
        AddOnEntity $installedAddOn,
        array $json,
        array &$stateChanges
    )
    {
        static::applyAddOnPostInstallation($addOn, $installedAddOn, $json, $stateChanges);
    }

    /**
     * Called when the post-upgrade code for an add-on has been run.
     *
     * Event hint: The add-on ID for the add-on being upgraded.
     *
     * @param AddOn       $addOn          The AddOn object for the add-on being upgraded.
     * @param AddOnEntity $installedAddOn The existing add-on entity.
     * @param array       $json           An array decoded from the add-on's addon.json file.
     * @param array       $stateChanges   An array for storing state changes such as post-upgrade controller redirects.
     */
    public static function addonPostUpgrade(
        AddOn $addOn,
        AddOnEntity $installedAddOn,
        array $json,
        array &$stateChanges
    )
    {
        static::applyAddOnPostInstallation($addOn, $installedAddOn, $json, $stateChanges);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected static function applyAddOnPostInstallation(
        AddOn $addOn,
        AddOnEntity $addonEntity,
        array $json,
        array &$stateChanges = null
    )
    {
        if (empty(Setup::$supportedAddOns[$addOn->getAddOnId()]))
        {
            return;
        }

        // kick off the installer
        $setup = new Setup($addOn, \XF::app());
        $setup->installStep2();
    }
}