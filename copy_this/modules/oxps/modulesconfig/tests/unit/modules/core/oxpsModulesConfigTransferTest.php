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
 * Class oxpsModulesConfigTransferTest
 * Tests for core class oxpsModulesConfigTransfer.
 *
 * @see oxpsModulesConfigTransfer
 */
class oxpsModulesConfigTransferTest extends OxidTestCase
{

    /**
     * Subject under the test.
     *
     * @var oxpsModulesConfigTransfer
     */
    protected $SUT;


    /**
     * Set SUT state before test.
     */
    public function setUp()
    {
        parent::setUp();

        $this->SUT = $this->getMock(
            'oxpsModulesConfigTransfer',
            array('__call', 'getConfig', '_jsonDownload', '_jsonBackup', '_touchBackupsDir')
        );
    }

    /**
     * Data provider for request and export data.
     *
     * @return array
     */
    public function exportDataProvider()
    {
        return array(
            // No request data
            array(array(), array()),

            // No modules requested
            array(array('modules' => array(), 'settings' => array('version')), array()),

            // No settings requested
            array(array('modules' => array('mymodule'), 'settings' => array()), array('mymodule' => array())),

            // One module, one setting requested
            array(
                array('modules' => array('mymodule'), 'settings' => array('version')),
                array('mymodule' => array('version' => '_SETTING_'))
            ),

            // Two modules, three settings requested
            array(
                array(
                    'modules'  => array('mymodule', 'othermodule'),
                    'settings' => array('version', 'extend', 'files')
                ),
                array(
                    'mymodule'    => array('version' => '_SETTING_', 'extend' => '_SETTING_', 'files' => '_SETTING_'),
                    'othermodule' => array('version' => '_SETTING_', 'extend' => '_SETTING_', 'files' => '_SETTING_'),
                )
            ),
        );
    }

    /**
     * Data provider for import abd request data: invalid data cases.
     *
     * @return array
     */
    public function invalidImportDataProvider()
    {
        return array(

            // No import data
            array(array(), array('modules' => array('my_module'), 'settings' => array('version'))),

            // Import data has no modules
            array(
                array(
                    '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                        'sShopVersion' => '5.2.0', 'sShopEdition' => 'EE', 'sShopId' => 1,
                    )
                ),
                array('modules' => array('my_module'), 'settings' => array('version'))
            ),

            // Request data has no modules
            array(
                array(
                    '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                        'sShopVersion' => '5.2.0', 'sShopEdition' => 'EE', 'sShopId' => 1,
                        'aModules'     => array('my_module' => array('version' => '1.2.3'))
                    )
                ),
                array('modules' => array(''), 'settings' => array('version'))
            ),

            // Request data has no settings
            array(
                array(
                    '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                        'sShopVersion' => '5.2.0', 'sShopEdition' => 'EE', 'sShopId' => 1,
                        'aModules'     => array('my_module' => array('version' => '1.2.3'))
                    )
                ),
                array('modules' => array('my_module'), 'settings' => array())
            ),

            // Import data has no requested modules
            array(
                array(
                    '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                        'sShopVersion' => '5.2.0', 'sShopEdition' => 'EE', 'sShopId' => 1,
                        'aModules'     => array('other_module' => array('version' => '1.2.3'))
                    )
                ),
                array('modules' => array('my_module'), 'settings' => array('version'))
            ),

            // Import data has no requested settings
            array(
                array(
                    '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                        'sShopVersion' => '5.2.0', 'sShopEdition' => 'EE', 'sShopId' => 1,
                        'aModules'     => array('my_module' => array('files' => array('class' => 'my/module/class')))
                    )
                ),
                array('modules' => array('my_module'), 'settings' => array('version'))
            ),
        );
    }


    public function testGetImportData_nothingSet_returnEmptyArray()
    {
        $this->assertSame(array(), $this->SUT->getImportData());
    }

    public function testGetImportData_importDataSet_returnTheData()
    {
        $this->SUT->setImportData(array('_my_import_data' => array('some_data')));

        $this->assertSame(array('_my_import_data' => array('some_data')), $this->SUT->getImportData());
    }


    /**
     * @dataProvider exportDataProvider
     */
    public function testExportForDownload(array $aRequestData, array $aExpectedModulesExportData)
    {
        // Config mock
        $oConfig = $this->getMock('oxConfig', array('getVersion', 'getEdition', 'getShopId'));
        $oConfig->expects($this->once())->method('getVersion')->will($this->returnValue('5.2.0'));
        $oConfig->expects($this->once())->method('getEdition')->will($this->returnValue('PE'));
        $oConfig->expects($this->once())->method('getShopId')->will($this->returnValue(2));

        // Configuration storage mock
        $oConfigStorage = $this->getMock('oxpsModulesConfigStorage', array('__call', 'load'));
        $oConfigStorage->expects($this->any())->method('load')->will($this->returnValue('_SETTING_'));

        oxRegistry::set('oxpsModulesConfigStorage', $oConfigStorage);

        $this->SUT->expects($this->once())->method('getConfig')->will($this->returnValue($oConfig));
        $this->SUT->expects($this->once())->method('_jsonDownload')->with(
            $this->stringEndsWith('.json'),
            $this->equalTo(
                array(
                    '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                        'sShopVersion' => '5.2.0',
                        'sShopEdition' => 'PE',
                        'sShopId'      => 2,
                        'aModules'     => $aExpectedModulesExportData,
                    )
                )
            )
        );

        $this->SUT->exportForDownload($aRequestData);
    }


    public function testBackupToFile()
    {
        // Config mock
        $oConfig = $this->getMock('oxConfig', array('getVersion', 'getEdition', 'getShopId'));
        $oConfig->expects($this->once())->method('getVersion')->will($this->returnValue('5.1.0'));
        $oConfig->expects($this->once())->method('getEdition')->will($this->returnValue('EE'));
        $oConfig->expects($this->once())->method('getShopId')->will($this->returnValue(1));
        modConfig::getInstance()->setConfigParam('sShopDir', '/var/www/my_shop/');

        // Configuration storage mock
        $oConfigStorage = $this->getMock('oxpsModulesConfigStorage', array('__call', 'load'));
        $oConfigStorage->expects($this->at(0))->method('load')->with('mymodule', 'version')->will(
            $this->returnValue('1.1.0')
        );
        $oConfigStorage->expects($this->at(1))->method('load')->with('mymodule', 'extend')->will(
            $this->returnValue(array())
        );
        $oConfigStorage->expects($this->at(2))->method('load')->with('mymodule', 'files')->will(
            $this->returnValue(array('mymoduleitem' => 'my/module/models/mymoduleitem.php'))
        );
        $oConfigStorage->expects($this->at(3))->method('load')->with('othermodule', 'version')->will(
            $this->returnValue('0.1.0')
        );
        $oConfigStorage->expects($this->at(4))->method('load')->with('othermodule', 'extend')->will(
            $this->returnValue(
                array(
                    'basket'    => 'other/module/controllers/othermodulebasket',
                    'oxarticle' => 'other/module/models/othermoduleoxarticle',
                )
            )
        );
        $oConfigStorage->expects($this->at(5))->method('load')->with('othermodule', 'files')->will(
            $this->returnValue(array())
        );

        oxRegistry::set('oxpsModulesConfigStorage', $oConfigStorage);

        $this->SUT->expects($this->exactly(2))->method('getConfig')->will($this->returnValue($oConfig));
        $this->SUT->expects($this->never())->method('_jsonDownload');
        $this->SUT->expects($this->once())->method('_touchBackupsDir')->with(
            '/var/www/my_shop/export/modules_config/'
        );
        $this->SUT->expects($this->once())->method('_jsonBackup')->with(
            $this->stringEndsWith('.my_backup.json'),
            $this->equalTo(
                array(
                    '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                        'sShopVersion' => '5.1.0',
                        'sShopEdition' => 'EE',
                        'sShopId'      => 1,
                        'aModules'     => array(
                            'mymodule'    => array(
                                'version' => '1.1.0',
                                'extend'  => array(),
                                'files'   => array('mymoduleitem' => 'my/module/models/mymoduleitem.php'),
                            ),
                            'othermodule' => array(
                                'version' => '0.1.0',
                                'extend'  => array(
                                    'basket'    => 'other/module/controllers/othermodulebasket',
                                    'oxarticle' => 'other/module/models/othermoduleoxarticle',
                                ),
                                'files'   => array(),
                            )
                        ),
                    )
                )
            )
        )->will($this->returnValue(888));

        $this->assertSame(
            888,
            $this->SUT->backupToFile(
                array(
                    'modules'  => array('mymodule', 'othermodule'),
                    'settings' => array('version', 'extend', 'files')
                ),
                'my_backup'
            )
        );
    }


    public function testGetImportDataValidationErrors()
    {
        // Config mock
        $oConfig = $this->getMock('oxConfig', array('getVersion', 'getEdition', 'getShopId'));
        $oConfig->expects($this->once())->method('getVersion')->will($this->returnValue('4.8.0'));
        $oConfig->expects($this->once())->method('getEdition')->will($this->returnValue('CE'));
        $oConfig->expects($this->once())->method('getShopId')->will($this->returnValue(1));

        // Import data validator mock
        /** @var oxpsModulesConfigJsonValidator $oValidator */
        $oValidator = $this->getMock('oxpsModulesConfigJsonValidator', array('__call', 'init', 'validate'));
        $oValidator->expects($this->once())->method('init')->with(
            array(
                '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                    'sShopVersion' => '5.2.0',
                    'sShopEdition' => 'EE',
                    'sShopId'      => 1,
                    'aModules'     => array('mymodule' => array('version' => '2.0.0-beta')),
                )
            ),
            array(
                '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                    'sShopVersion' => '4.8.0',
                    'sShopEdition' => 'CE',
                    'sShopId'      => 1,
                    'aModules'     => array(),
                )
            )
        );
        $oValidator->expects($this->once())->method('validate')->will(
            $this->returnValue(array('ERR_SHOP_VERSION_WRONG', 'ERR_SHOP_EDITION_WRONG'))
        );

        oxRegistry::set('oxpsModulesConfigJsonValidator', $oValidator);

        $this->SUT->expects($this->once())->method('getConfig')->will($this->returnValue($oConfig));
        $this->SUT->setImportData(
            array(
                '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                    'sShopVersion' => '5.2.0',
                    'sShopEdition' => 'EE',
                    'sShopId'      => 1,
                    'aModules'     => array('mymodule' => array('version' => '2.0.0-beta')),
                )
            )
        );

        $this->assertSame(
            array('ERR_SHOP_VERSION_WRONG', 'ERR_SHOP_EDITION_WRONG'),
            $this->SUT->getImportDataValidationErrors()
        );
    }


    /**
     * @dataProvider invalidImportDataProvider
     */
    public function testImportData_importOrRequestDataInvalidOrDoesNotMatch_returnFalseAndUpdateNoneConfiguration(
        array $aImportData, array $aRequestData
    )
    {
        // Configuration storage mock
        $oConfigStorage = $this->getMock('oxpsModulesConfigStorage', array('__call', 'save'));
        $oConfigStorage->expects($this->never())->method('save');

        oxRegistry::set('oxpsModulesConfigStorage', $oConfigStorage);

        $this->SUT->setImportData($aImportData);

        $this->assertFalse($this->SUT->importData($aRequestData));
    }

    public function testImportData_importAndRequestDataMatch_returnTrueAndUpdateConfigurationWithRequestedImportData()
    {
        // Configuration storage mock
        $oConfigStorage = $this->getMock('oxpsModulesConfigStorage', array('__call', 'save'));
        $oConfigStorage->expects($this->at(0))->method('save')->with(
            'my_module',
            'files',
            array('class' => 'my/module/class')
        );
        $oConfigStorage->expects($this->at(1))->method('save')->with(
            'other_module',
            'version',
            '8.0.1'
        );

        oxRegistry::set('oxpsModulesConfigStorage', $oConfigStorage);

        $this->SUT->setImportData(
            array(
                '_OXID_ESHOP_MODULES_CONFIGURATION_' => array(
                    'sShopVersion' => '5.2.0', 'sShopEdition' => 'EE', 'sShopId' => 1,
                    'aModules'     => array(
                        'my_module'    => array(
                            'files' => array('class' => 'my/module/class')
                        ),
                        'other_module' => array(
                            'version' => '8.0.1'
                        ),
                        'third_module' => array(
                            'version' => '3.3.3',
                            'files'   => array('class3' => '3rd/module/class3')
                        ),
                    )
                )
            )
        );

        $this->assertTrue(
            $this->SUT->importData(
                array(
                    'modules'  => array('my_module', 'other_module', 'third_module'),
                    'settings' => array('version', 'files')
                )
            )
        );
    }


    public function testGetImportErrors()
    {
        $this->assertSame(array(), $this->SUT->getImportErrors(), 'No import errors are checked in this version.');
    }
}
