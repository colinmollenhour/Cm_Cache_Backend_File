<?php

require_once 'app/Mage.php'; // Just for autoloading
require_once 'CommonExtendedBackendTest.php';

/**
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @group      Zend_Cache
 */
class Cm_Cache_FileBackendTest extends Zend_Cache_CommonExtendedBackendTest {

    protected $_instance;
    protected $_cache_dir;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct('Cm_Cache_Backend_File', $data, $dataName);
    }

    public function setUp($notag = false)
    {
        $this->mkdir();
        $this->_instance = new Cm_Cache_Backend_File(array(
            'cache_dir' => $this->getTmpDir() . DIRECTORY_SEPARATOR,
        ));
        parent::setUp($notag);
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->_instance);
    }

    public function testConstructorBadOption() { }
    public function testConstructorCorrectCall() { }

    public function testGetWithANonExistingCacheIdAndANullLifeTime()
    {
        $this->_instance->setDirectives(array('lifetime' => null));
        $this->assertFalse($this->_instance->load('barbar'));
    }

    public function testSaveCorrectCallWithHashedDirectoryStructure()
    {
        $this->_instance->setOption('hashed_directory_level', 2);
        $res = $this->_instance->save('data to cache', 'foo', array('tag1', 'tag2'));
        $this->assertTrue($res);
    }

    public function testCleanModeAllWithHashedDirectoryStructure()
    {
        $this->_instance->setOption('hashed_directory_level', 2);
        $this->assertTrue($this->_instance->clean('all'));
        $this->assertFalse($this->_instance->test('bar'));
        $this->assertFalse($this->_instance->test('bar2'));
    }

    public function testSaveWithABadCacheDir()
    {
        $this->_instance->setOption('cache_dir', '/foo/bar/lfjlqsdjfklsqd/');
        $res = $this->_instance->save('data to cache', 'foo', array('tag1', 'tag2'));
        $this->assertFalse($res);
    }

}
