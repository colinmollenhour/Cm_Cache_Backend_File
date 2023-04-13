Cm_Cache_Backend_File
=====================

The stock `Zend_Cache_Backend_File` backend has extremely poor performance for
cleaning by tags making it become unusable as the number of cached items
increases. This backend makes many changes resulting in a huge performance boost,
especially for tag cleaning.

This cache backend works by indexing tags in files so that tag operations
do not require a full scan of every cache file. The ids are written to the
tag files in append-only mode and only when files exceed 4k and only randomly
are the tag files compacted to prevent endless growth in edge cases.

The metadata and the cache record are stored in the same file rather than separate
files resulting in fewer inodes and fewer file stat/read/write/lock/unlink operations.
Also, the original hashed directory structure had very poor distribution due to
the adler32 hashing algorithm and prefixes. The multi-level nested directories
have been dropped in favor of single-level nesting made from multiple characters.

Is the improvement substantial? Definitely. Tag cleaning is literally thousands of
times faster, loading is twice as fast, and saving is slightly slower dependent on
the number of tags being saved.

Test it for yourself with the [Magento Cache Benchmark](https://github.com/colinmollenhour/magento-cache-benchmark).

Installation
------------

1. Install with Composer: `composer require colinmollenhour/cache-backend-file`
2. Edit `app/etc/local.xml` changing `global/cache/backend` to `Cm_Cache_Backend_File` (Magento 1 / OpenMage)
3. Delete all contents of the cache directory

Example Configuration
---------------------

```xml
<config>
    <global>
        <cache>
            <backend>Cm_Cache_Backend_File</backend>
        </cache>
        ...
    </global>
    ...
</config>
```

By default, `Cm_Cache_Backend_File` is configured *not* to use chmod to set file permissions. The
proper way to do file permissions is to respect the umask and not set any permissions. This way
the file permissions can be properly inherited using the OS conventions. To improve security the
umask should be properly set. In Magento the umask is set in `index.php` as 0 which means no
restrictions. So, for example to make files and directories no longer public add `umask(0007)` to
`Mage.php`.

If umasks are too complicated and you prefer the sub-optimal (less-secure, needless system calls)
approach you can enable the legacy chmod usage as seen below. This will force the file modes to be
set regardless of the umask.

```xml
<config>
    <global>
        <cache>
            <backend>Cm_Cache_Backend_File</backend>
            <backend_options>
                <use_chmod>1</use_chmod>
                <directory_mode>0777</directory_mode>
                <file_mode>0666</file_mode>
            </backend_options>
        </cache>
        ...
    </global>
    ...
</config>
```

For `directory_mode` the setgid bit can be set using 2 for the forth digit. E.g. 02770. This
will cause files and directories created within the directory with the setgid bit to inherit the
same group as the parent which is useful if you run scripts as users other than your web server user.
The setgid bit can also be used with the default configuration (use_chmod off) by simply setting
the bit on the var/cache directory one time using `chmod g+s var/cache`.

Note that running your cron job as root is not a good practice from a security standpoint.

Cleaning Old Files
------------------

Magento and Zend_Cache do not cleanup old records by themselves so if you want to
keep your cache directory tidy you need to write and invoke regularly your own script
which cleans the old data. Here is an example for Magento:

```php
<?php PHP_SAPI == 'cli' or die('<h1>:P</h1>');
ini_set('memory_limit','1024M');
set_time_limit(0);
error_reporting(E_ALL | E_STRICT);
require_once 'app/Mage.php';
Mage::app()->getCache()->getBackend()->clean(Zend_Cache::CLEANING_MODE_OLD);
// uncomment this for Magento Enterprise Edition
// Enterprise_PageCache_Model_Cache::getCacheInstance()->getFrontend()->getBackend()->clean(Zend_Cache::CLEANING_MODE_OLD);
```

Development
-----------

Please feel free to send Pull Requests to give back your improvements to the community!

You can run the unit tests locally with just Docker installed using a simple alias:

```shell
alias cm-cache-backend-file='docker run --rm -it -u $(id -u):$(id -g) -v ${COMPOSER_HOME:-$HOME/.composer}:/tmp -v $(pwd):/app --workdir /app cm-cache-backend-file'
docker build . -t cm-cache-backend-file
```

Then, install Composer dependencies and run tests like so: 
```shell
  cm-cache-backend-file composer install
  cm-cache-backend-file composer run-script test
  cm-cache-backend-file composer run-script php-cs-fixer -- --dry-run
```

Special Thanks
--------------

Thanks to Vinai Kopp for the inspiring this backend with your symlink rendition!

```
@copyright  Copyright (c) 2012 Colin Mollenhour (http://colin.mollenhour.com)
This project is licensed under the "New BSD" license (see source).
```
