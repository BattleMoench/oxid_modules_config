<?php
/**
 * This file is part of OXID Module Configuration Im-/Exporter module.
 *
 * OXID Module Configuration Im-/Exporter module is free software:
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * OXID Module Configuration Im-/Exporter module is distributed in
 * the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
 * for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID Module Configuration Im-/Exporter module.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @category      module
 * @package       modulesconfig
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 */
use Symfony\Component\Yaml\Yaml;

/**
 * Class oxpsModulesConfigConfigExport
 * Implements functionality for the oxpsConfigExportCommand
 */
class oxpsModulesConfigConfigExport extends OxpsConfigCommandBase
{

    /*
     * executes all functionality which is necessary for a call of OXID console config:import
     *
     */
    public function executeConsoleCommand()
    {
        $this->init();

        $aReturn = $this->getConfigValues($this->aConfiguration['excludeFields'], false);

        $aReturn = $this->addModuleOrder($aReturn);

        try{
            $aShops = $this->writeDataToFileSeperatedByShop($this->getConfigDir(), $aReturn);

            // get environment specific config values
            $aReturn = $this->getConfigValues($this->aConfiguration['envFields'], true);

            // write environment specific config values to files
            $this->writeDataToFileSeperatedByShop($this->getEnviromentConfigDir(), $aReturn);

            $aMetaConfigFile['shops']                 = $aShops;
            $aMetaConfigFile[$this->sNameForMetaData] = $this->aDefaultConfig[$this->sNameForMetaData];

            $this->writeDataToFile($this->getShopsConfigFileName(), $aMetaConfigFile);

            $this->getDebugOutput()->writeLn("done");
        } catch(RuntimeException $e){
            $this->getDebugOutput()->writeLn("Could not complete");
            $this->getDebugOutput()->writeLn($e->getMessage());
        }
    }

    /**
     * Loads the old config file, reads the order of modules and store it
     *
     * @param $aReturn
     *
     * @return mixed
     */
    protected
    function addModuleOrder($aReturn)
    {
        return $aReturn;
    }

    /**
     * @param array $aConfigFields
     * @param bool $blIncludeMode if true include the fields, else exclude them.
     *
     * @return array
     */
    protected
    function getConfigValues($aConfigFields, $blIncludeMode)
    {
        $sIncludeMode = $blIncludeMode ? '' : 'NOT';
        $sSql = "SELECT oxvarname, oxvartype, %s as oxvarvalue, oxmodule, oxshopid from oxconfig
                 WHERE oxvarname $sIncludeMode IN ('%s') order by oxshopid asc, oxmodule ASC, oxvarname ASC";

        $sSql = sprintf(
            $sSql,
            oxRegistry::getConfig()->getDecodeValueQuery(),
            implode("', '", $aConfigFields)
        );

        $aConfigValues = oxDb::getDb(oxDb::FETCH_MODE_ASSOC)->getAll($sSql);
        $aGroupedValues = $this->groupValues($aConfigValues);
        $this->addShopConfig($aGroupedValues, $aConfigFields, $blIncludeMode);
        $aGroupedValues = $this->withoutDefaults($aGroupedValues);

        return $aGroupedValues;
    }

    /**
     * @param $aGroupedValues
     * @param $aConfigFields
     * @param $blInclude_mode
     */
    protected
    function addShopConfig(& $aGroupedValues, $aConfigFields, $blInclude_mode)
    {
        $aShops = oxDb::getDb(oxDb::FETCH_MODE_ASSOC)->getAll('SELECT * FROM `oxshops` ORDER BY oxid ASC');
        foreach ($aShops as $aShop) {
            $id = $aShop['OXID'];
            unset ($aShop['OXID']);
            unset ($aShop['OXTIMESTAMP']);
            foreach ($aShop as $sVarName => $sVarValue) {
                $blFieldConfigured = array_key_exists($sVarName, $aConfigFields);
                $blIncludeFiled = $blInclude_mode && $blFieldConfigured;
                $blIncludeFiled = $blIncludeFiled || (!$blInclude_mode && !$blFieldConfigured);
                if ($blIncludeFiled) {
                    $aGroupedValues[$id]['oxshops'][$sVarName] = $sVarValue;
                }
            }
        }
    }

    protected
    function withoutDefaults(&$aGroupedValues)
    {
        foreach ($aGroupedValues as $sShopId => &$aShopConfig) {

            $aModuleConfigs = &$aShopConfig['module'];

            /** @var oxModule $oModule */
            $oModule = oxNew('oxModule');

            if (isset($aModuleConfigs)) {
                foreach ($aModuleConfigs as $sModuleId => &$aModuleConfig) {

                    if (!$oModule->load($sModuleId)) {
                        $oDebugOutput = $this->getDebugOutput();
                        $oDebugOutput->writeLn("[DEBUG] {$sModuleId} does not exist - skipping");
                        continue;
                    }
                    $aDefaultModuleSettings = $oModule->getInfo("settings");
                    foreach ($aDefaultModuleSettings as $aConfigValue) {
                        $sVarName = $aConfigValue['name'];
                        $sDefaultType = $aConfigValue['type'];
                        $mDefaultValue = $aConfigValue['value'];

                        $mCurrentValue = $aModuleConfig[$sVarName];

                        if ($sDefaultType == 'bool') {
                            if ($mDefaultValue === 'false') {
                                $mDefaultValue = '';
                            } else {
                                $mDefaultValue = $mDefaultValue ? '1' : '';
                            }
                        }

                        if ($mCurrentValue === $mDefaultValue) {
                            unset($aModuleConfig[$sVarName]);
                            if (count($aModuleConfig) == 0) {
                                unset($aModuleConfigs[$sModuleId]);
                            }
                        }
                    }
                }
            }
            $aDefaultGeneralConfig = $this->aDefaultConfig[$this->sNameForGeneralShopSettings];
            $aGeneralConfig = &$aShopConfig[$this->sNameForGeneralShopSettings];
            foreach ($aGeneralConfig as $sVarName => $mCurrentValue) {
                $mDefaultValue = $aDefaultGeneralConfig[$sVarName];
                if ($mCurrentValue === $mDefaultValue) {
                    unset($aGeneralConfig[$sVarName]);
                }
            }

            if (array_key_exists('theme', $aShopConfig)) {
                $aCurrentThemeConfigs = &$aShopConfig['theme'];
                $aDefaultThemeConfigs = $this->aDefaultConfig['theme'];
                foreach ($aCurrentThemeConfigs as $sTheme => &$aThemeConfig) {
                    $aDefaultThemeConfig = $aDefaultThemeConfigs[$sTheme];
                    if ($aDefaultThemeConfig != null) {
                        foreach ($aThemeConfig as $sVarName => $mCurrentValue) {
                            $mDefaultValue = $aDefaultThemeConfig[$sVarName];
                            if ($mCurrentValue === $mDefaultValue) {
                                unset($aThemeConfig[$sVarName]);
                                if (count($aThemeConfig) == 0) {
                                    unset($aCurrentThemeConfigs[$sTheme]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $aGroupedValues;
    }

    protected
    function groupValues($aConfigValues)
    {
        $aGroupedValues = [];
        foreach ($aConfigValues as $k => $aConfigValue) {
            $sShopId = $aConfigValue['oxshopid'];
            $sVarName = $aConfigValue['oxvarname'];
            $sVarType = $aConfigValue['oxvartype'];
            $mVarValue = $aConfigValue['oxvarvalue'];
            $sModule = $aConfigValue['oxmodule'];
            $aParts = explode(':', $sModule);
            $sSection = $aParts[0];
            $sModule = $aParts[1];

            if (in_array($sVarName, ['aDisabledModules'])) {
                if ($sVarType !== 'arr') {
                    $this->oOutput->writeLn("[error] $sVarName corrupted vartype: '$sVarType' converted to arr");
                    $sVarType = 'arr';
                }
            }

            if (in_array($sVarType, ['aarr', 'arr'])) {
                $mVarValue = unserialize($mVarValue);
                if (!is_array($mVarValue)) {
                    $this->oOutput->writeLn("[error] $sVarName is not array: '$mVarValue' convert to empty array");
                    $mVarValue = array();
                }
            }

            if (!$sModule) {
                if ($sVarName === 'aModuleVersions') {
                    //aModuleVersions is needed to compare the version on config import so you can be warned
                    //if the import does not match the code version and may be wrong or have wrong assumptions
                    // about module defaults
                }

                //restored from module metadata by import:
                if (in_array(
                    $sVarName,
                    ['aModuleFiles', 'aModuleEvents', 'aModuleTemplates', 'aModulePaths']
                )) {
                    continue;
                }

                //force conversation to normal arrays and sort values, this is needed because sometime this arrays
                //becomes associative arrays when oxid shop modifies them. {'1'=>'oepaypal'} to [oepaypal]
                if (in_array($sVarName, ['aDisabledModules'])) {
                    $mVarValue = array_values($mVarValue);
                    sort($mVarValue);
                }

                //only export module info if the the order may be important
                // (and thats the fact if there is more the one module in the string)
                if ($sVarName == 'aModules') {
                    $aModules = $mVarValue;
                    $aModulesTmp = [];
                    foreach ($aModules as $sBaseClass => $sAmpSeparatedClassNames) {
                        if (strpos($sAmpSeparatedClassNames, '&') !== false) {
                            $aClassNames = explode("&", $sAmpSeparatedClassNames);
                            $aModulesTmp[$sBaseClass] = $aClassNames;
                        }
                    }
                    $mVarValue = $aModulesTmp;
                }

                // the following options can be sorted so they have a stable order between exports,
                // that makes merging easier
                if (in_array($sVarName, ['aModules'])) {
                    ksort($mVarValue);
                }

                $mVarValue = $this->varValueWithTypeInfo(
                    $sVarName,
                    $mVarValue,
                    $sVarType
                );
                $sSection = $this->sNameForGeneralShopSettings;
                $aGroupedValues[$sShopId][$sSection][$sVarName] =
                    $mVarValue;
            } else {
                if ($sSection != 'module') {
                    $mVarValue = $this->varValueWithTypeInfo($sVarName, $mVarValue, $sVarType);
                }
                $aGroupedValues[$sShopId][$sSection][$sModule][$sVarName] =
                    $mVarValue;
            }
        }

        return $aGroupedValues;
    }

    protected
    function varValueWithTypeInfo($sVarName, $mVarValue, $sVarType)
    {
        if ($sVarType === 'aarr' && count($mVarValue) > 1) {
            //if array contain more then one item it can be distiglished from the assoc array we use for type
        } elseif ($sVarType === 'arr') {
            // arrays can be recognised
        } else {
            // default type
            $typeInfoNeeded = true;
            if ($sVarType == 'str' || $sVarType == 'bool') {
                $typeInfoNeeded = false;
                if (substr($sVarName, 0, 2) === "bl") {
                    if ($sVarType !== 'bool') {
                        $typeInfoNeeded = true;
                    }
                }
            }

            if ($typeInfoNeeded) {
                $mVarValue = array($sVarType => $mVarValue);
            }
        }

        return $mVarValue;
    }

    /**
     * @param string $sFileName
     * @param array $aData
     */
    protected
    function writeDataToFileSeperatedByShop($sDirName, $aData)
    {
        $aShops = array();
        foreach ($aData as $sShop => $aShopConfig) {
            $sFileName = '/' . 'shop' . $sShop . '.' . $this->getFileExt();
            $aShops[$sShop] = $sFileName;
            $this->writeDataToFile(
                $sDirName . $sFileName,
                $aShopConfig
            );
        }

        return $aShops;
    }

    /**
     * @param string $sFileName
     * @param array $aData
     */
    protected
    function writeDataToFile($sFileName, $aData)
    {
        $exportFormat = $this->getExportFormat();
        if ($exportFormat == 'json') {
            $this->writeToJsonFile($sFileName, $aData);
        } elseif ($exportFormat == 'yaml') {
            $this->writeStringToFile($sFileName, Yaml::dump($aData, 5));
        }
    }

    /**
     * @param string $sFileName
     * @param array $aData
     */
    protected
    function writeToJsonFile($sFileName, $aData)
    {
        $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $this->writeStringToFile($sFileName, json_encode($aData, $options));
    }

    /**
     * @param string $sFileName
     * @param string $sData
     *
     * @throws RuntimeException
     */
    protected
    function writeStringToFile($sFileName, $sData)
    {
        $sMode = 'w';
        if ($sFileName && $sData) {
            $oFile = new SplFileObject($sFileName, $sMode);
            $oFile->fwrite($sData);
        }
    }
}