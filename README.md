Cm_Cache_Backend_File
=====================

The stock Zend_Cache_Backend_File backend has extremely poor performance for
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

1. Clone module with [modman](https://github.com/colinmollenhour/modman)
2. Edit `app/etc/local.xml` changing `global/cache/backend` to `Cm_Cache_Backend_File`
3. Delete all contents of the cache directory

Two Level Cache with APC for single node configuration

    <!-- This is a child node of config/global -->
    <cache>
      <backend>Zend_Cache_Backend_TwoLevels</backend>
      <backend_options>
        <fast_backend>Apc</fast_backend>
        <slow_backend>Cm_Cache_Backend_File</slow_backend>
	      <slow_backend_options>
		      <cache_dir>var/cache</cache_dir>
		      <file_name_prefix>mage</file_name_prefix>
		      <hashed_directory_umask>0777</hashed_directory_umask>
		      <cache_file_umask>0777</cache_file_umask>
	      </slow_backend_options>
	      <slow_backend_custom_naming>1</slow_backend_custom_naming>
	    </backend_options>
    </cache>
    
    <!-- This is a child node of config/global for Magento Enterprise FPC -->
    <full_page_cache>
      <backend>Zend_Cache_Backend_TwoLevels</backend>
	    <backend_options>
	      <fast_backend>Apc</fast_backend>
	      <slow_backend>Cm_Cache_Backend_File</slow_backend>
	      <slow_backend_options>
		      <cache_dir>var/full_page_cache</cache_dir>
		      <file_name_prefix>mage_fpc</file_name_prefix>
		      <hashed_directory_umask>0777</hashed_directory_umask>
		      <cache_file_umask>0777</cache_file_umask>
	       </slow_backend_options>
	       <slow_backend_custom_naming>1</slow_backend_custom_naming>
	     </backend_options>
     </full_page_cache>

Special Thanks
--------------

Thanks to Vinai Kopp for the inspiring this backend with your symlink rendition!

```
@copyright  Copyright (c) 2012 Colin Mollenhour (http://colin.mollenhour.com)
This project is licensed under the "New BSD" license (see source).
```
