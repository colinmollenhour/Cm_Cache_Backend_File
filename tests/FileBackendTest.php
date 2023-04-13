<?php
/*
==New BSD License==

Copyright (c) 2012, Colin Mollenhour
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * The name of Colin Mollenhour may not be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once 'CommonExtendedBackend.php';

/**
 * @copyright  Copyright (c) 2012 Colin Mollenhour (http://colin.mollenhour.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class FileBackendTest extends \CommonExtendedBackend
{
    protected $_instance;
    protected $_cache_dir;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct('Cm_Cache_Backend_File', $data, $dataName);
    }

    public function setUp(): void
    {
        $this->mkdir();
        $this->_instance = new Cm_Cache_Backend_File(array(
            'cache_dir' => $this->getTmpDir() . DIRECTORY_SEPARATOR,
        ));
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->_instance);
    }

    public function testConstructorBadOption()
    {
        $this->markTestSkipped();
    }
    public function testConstructorCorrectCall()
    {
        $this->markTestSkipped();
    }

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

    public function testSaveWithNullLifeTime2()
    {
        $res = $this->_instance->save('data to cache', 'foo', array('tag1', 'tag2'), null);
        $this->assertTrue($res);
        $metadatas = $this->_instance->getMetadatas('foo');
        $this->assertGreaterThan(time() + 99999999, $metadatas['expire']);
    }
}
