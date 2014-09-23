<?php
/**
 * This Software is the property of OXID eSales and is protected
 * by copyright law - it is NOT Freeware.
 *
 * Any unauthorized use of this software without a valid license key
 * is a violation of the license agreement and will be prosecuted by
 * civil and criminal law.
 *
 * @category      module
 * @package       modulesconfig
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 */

/**
 * Class oxpsModulesConfigStorage
 * A model for modules related configuration loading and saving methods.
 */
class oxpsModulesConfigStorage extends oxConfig
{

    /**
     * Settings map.
     * Maps setting name from metadata to its class name, storage type or table settings is stored in and
     * a key that defines setting in its storage location.
     *
     * @var array
     */
    protected $_settingsMap = array(
        'version'   => array('oxConfig', 'aModuleVersions'),
        'extend'    => array('oxConfig-Global', 'aModules'),
        'files'     => array('oxConfig', 'aModuleFiles'),
        'templates' => array('oxConfig', 'aModuleTemplates'),
        'blocks'    => array('oxtplblocks', '*'),
        'settings'  => array('oxConfig-List', '*'),
        'events'    => array('oxConfig', 'aModuleEvents'),
    );


    /**
     * Map setting and call its loader.
     *
     * @param string $sModuleId ID of a module setting is related to.
     * @param string $sSetting  Setting name as a key from module metadata file.
     *
     * @return mixed
     */
    public function load( $sModuleId, $sSetting )
    {
        list( $sSettingOrigin, $sSettingKey ) = $this->_mapSetting( $sSetting );

        return $this->_load( $sModuleId, $sSettingOrigin, $sSettingKey );
    }

    /**
     * Map setting and call its save method.
     *
     * @param string $sModuleId ID of a module setting is related to.
     * @param string $sSetting  Setting name as a key from module metadata file.
     * @param mixed  $mValue    A new value(s) to set.
     */
    public function save( $sModuleId, $sSetting, $mValue )
    {
        list( $sSettingOrigin, $sSettingKey ) = $this->_mapSetting( $sSetting );

        $this->_save( $sModuleId, $sSettingOrigin, $sSettingKey, $mValue );
    }


    /**
     * Get setting origin and key identifiers by metadata key name.
     *
     * @param string $sSetting
     *
     * @return array
     */
    protected function _mapSetting( $sSetting )
    {
        if ( !array_key_exists( $sSetting, $this->_settingsMap ) ) {
            return array('', '');
        }

        return $this->_settingsMap[$sSetting];
    }

    /**
     * Load a setting (settings list) by related module ID, setting origin identifier and setting key identifier.
     *
     * @param string $sModuleId      ID of a module setting is related to.
     * @param string $sSettingOrigin Name of class, storage type or table settings is stored in.
     * @param string $sSettingKey    A key that defines setting in its storage location.
     *
     * @return mixed
     */
    protected function _load( $sModuleId, $sSettingOrigin, $sSettingKey )
    {
        switch ( $sSettingOrigin ) {
            case 'oxConfig':
                $mSetting = $this->_loadFromShopConfig( $sModuleId, $sSettingKey );
                break;

            case 'oxConfig-Global':
                $mSetting = $this->_loadFromShopConfigAndSeparate( $sModuleId, $sSettingKey );
                break;

            case 'oxConfig-List':
                $mSetting = $this->_loadListFromShopConfig( $sModuleId );
                break;

            case 'oxtplblocks':
                $mSetting = $this->_loadFromBlocksTable( $sModuleId );
                break;

            default:
                $mSetting = null;
                break;
        }

        return $mSetting;
    }

    /**
     * Find a setting (settings list) by related module ID, setting origin identifier and setting key identifier and
     * update it with a new value(s).
     *
     * @param string $sModuleId      ID of a module setting is related to.
     * @param string $sSettingOrigin Name of class, storage type or table settings is stored in.
     * @param string $sSettingKey    A key that defines setting in its storage location.
     * @param mixed  $mValue         A new value(s) to set.
     */
    protected function _save( $sModuleId, $sSettingOrigin, $sSettingKey, $mValue )
    {
        switch ( $sSettingOrigin ) {
            case 'oxConfig':
                $mAllSettings             = $this->getShopConfVar( $sSettingKey );
                $mAllSettings[$sModuleId] = ( $sSettingKey === 'aModuleVersions' ) ? (string) $mValue : (array) $mValue;
                $this->saveShopConfVar( 'arr', $sSettingKey, $mAllSettings );
                break;

            case 'oxConfig-List':
                $this->_saveModuleSettings( $sModuleId, (array) $mValue );
                break;

            case 'oxtplblocks':
                $this->_saveModuleBlocks( $sModuleId, (array) $mValue );
                break;

            // TODO DDR other types and separate method for case "oxConfig"

            default:
                break;
        }
    }

    /**
     * Load module setting from shop configuration.
     *
     * @param string $sModuleId
     * @param string $sSettingKey
     *
     * @return mixed
     */
    protected function _loadFromShopConfig( $sModuleId, $sSettingKey )
    {
        $mAllSettings = $this->getShopConfVar( $sSettingKey );

        if ( is_array( $mAllSettings ) and array_key_exists( $sModuleId, $mAllSettings ) ) {
            return $mAllSettings[$sModuleId];
        }

        return null;
    }

    /**
     * Load combined modules setting from shop configuration and separate only requested module settings.
     *
     * @param string $sModuleId
     * @param string $sSettingKey
     *
     * @return array
     */
    protected function _loadFromShopConfigAndSeparate( $sModuleId, $sSettingKey )
    {
        $aExtendedClasses = (array) $this->getShopConfVar( $sSettingKey );

        //todo ddr: implement it somehow...

        return $aExtendedClasses;
    }

    /**
     * Load modules settings array from shop configuration.
     *
     * @param string $sModuleId
     *
     * @return array
     */
    protected function _loadListFromShopConfig( $sModuleId )
    {
        $oDb = oxDb::getDb( oxdb::FETCH_MODE_ASSOC );

        return (array) $oDb->getArray(
            sprintf(
                "SELECT `OXVARNAME`, `OXVARTYPE`, %s AS `OXVARVALUE` FROM `oxconfig` " .
                "WHERE `OXSHOPID` = %s AND `OXMODULE` = %s",
                $this->getDecodeValueQuery(),
                $oDb->quote( $this->getShopId() ),
                $oDb->quote( sprintf( "module:%s", $sModuleId ) )
            )
        );
    }

    /**
     * Load modules blocks settings array from database blocks table.
     *
     * @param string $sModuleId
     *
     * @return array
     */
    protected function _loadFromBlocksTable( $sModuleId )
    {
        $oDb = oxDb::getDb( oxdb::FETCH_MODE_ASSOC );

        return (array) $oDb->getArray(
            sprintf(
                "SELECT `OXACTIVE`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE` FROM `oxtplblocks` " .
                "WHERE `OXSHOPID` = %s AND `OXMODULE` = %s",
                $oDb->quote( $this->getShopId() ),
                $oDb->quote( $sModuleId )
            )
        );
    }

    /**
     * Update module settings in database with a settings import data.
     *
     * @param string $sModuleId
     * @param array  $aSettings
     */
    protected function _saveModuleSettings( $sModuleId, array $aSettings )
    {
        $oDb = oxDb::getDb();

        // Delete all settings for a module in current sub-shop from database
        $oDb->execute(
            sprintf(
                "DELETE FROM `oxconfig` WHERE `OXSHOPID` = %s AND `OXMODULE` = %s",
                $oDb->quote( $this->getShopId() ),
                $oDb->quote( sprintf( 'module:%s', $sModuleId ) )
            )
        );

        // Save module settings from import data to database
        foreach ( $aSettings as $aSetting ) {
            if ( !isset( $aSetting->OXVARTYPE, $aSetting->OXVARNAME, $aSetting->OXVARVALUE ) ) {
                continue;
            }

            $this->saveShopConfVar(
                $aSetting->OXVARTYPE,
                $aSetting->OXVARNAME,
                $aSetting->OXVARVALUE,
                null,
                sprintf( 'module:%s', $sModuleId )
            );
        }
    }

    /**
     * Update module blocks configuration in database with a block import data.
     *
     * @param string $sModuleId
     * @param array  $aBlocks
     */
    protected function _saveModuleBlocks( $sModuleId, array $aBlocks )
    {
        /** @var oxUtilsObject $oObjectUtils */
        $oObjectUtils = oxRegistry::get( 'oxUtilsObject' );

        $oDb = oxDb::getDb();

        // Delete all blocks for a module in current sub-shop from database
        $oDb->execute(
            sprintf(
                "DELETE FROM `oxtplblocks` WHERE `OXSHOPID` = %s AND `OXMODULE` = %s",
                $oDb->quote( $this->getShopId() ),
                $oDb->quote( $sModuleId )
            )
        );

        // Collect and insert blocks data to database
        $aInsertBlocks = array();

        foreach ( $aBlocks as $aBlock ) {
            $aInsertBlocks[] = sprintf(
                "(%s, %d, %s, %s, %s, %d, %s, %s)",
                $oDb->quote( $oObjectUtils->generateUId() ),
                (int) (bool) $aBlock->OXACTIVE,
                $oDb->quote( $this->getShopId() ),
                $oDb->quote( $aBlock->OXTEMPLATE ),
                $oDb->quote( $aBlock->OXBLOCKNAME ),
                (int) $aBlock->OXPOS,
                $oDb->quote( $aBlock->OXFILE ),
                $oDb->quote( $sModuleId )
            );
        }

        if ( !empty( $aInsertBlocks ) ) {
            $oDb->execute(
                "INSERT INTO `oxtplblocks` " .
                "(`OXID`, `OXACTIVE`, `OXSHOPID`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`) " .
                "VALUES " . implode( ", ", $aInsertBlocks )
            );
        }
    }
}
