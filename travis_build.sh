#!/usr/bin/env bash

root=$PWD
mkdir app
echo "<?php
require_once 'Zend/Exception.php';
require_once 'Zend/Cache.php';
require_once 'Zend/Cache/Backend/File.php';
require_once 'File.php';
" > app/Mage.php
mkdir -p Zend/Cache/Backend
mkdir -p Zend/Log/{Filter,Formatter,Writer}
cd $root/Zend
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Exception.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Cache.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log.php
cd $root/Zend/Cache
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Cache/Exception.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Cache/Backend.php
cd $root/Zend/Cache/Backend
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Cache/Backend/Interface.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Cache/Backend/ExtendedInterface.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Cache/Backend/File.php
cd $root/Zend/Log
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/FactoryInterface.php
cd $root/Zend/Log/Filter
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Filter/Priority.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Filter/Abstract.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Filter/Interface.php
cd $root/Zend/Log/Formatter
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Formatter/Simple.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Formatter/Abstract.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Formatter/Interface.php
cd $root/Zend/Log/Writer
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Writer/Stream.php
wget -q https://github.com/zendframework/zf1/raw/master/library/Zend/Log/Writer/Abstract.php
