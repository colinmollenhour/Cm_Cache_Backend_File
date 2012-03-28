<?php
/**
 * Cm_Cache_Backend_File
 *
 * This cache backend works by indexing tags in files so that tag operations
 * do not require a full scan of every cache file. The ids are written to the
 * tag files in append-only mode and only when files exceed 4k and only randomly
 * are the tag files compacted to prevent endless growth in edge cases.
 *
 * The stock Zend_Cache_Backend_File backend is anything but usable with any system
 * that uses tagging (such as Magento). This backend is nearly as fast with save()
 * and load() operations, but literally several orders of magnitude faster for any tag-related
 * operations. Benchmark tool here: https://github.com/colinmollenhour/magento-cache-benchmark
 *
 * Thanks to Vinai Kopp for the inspiring this backend with your symlink rendition!
 *
 * @copyright  Copyright (c) 2012 Colin Mollenhour (http://colin.mollenhour.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Cm_Cache_Backend_File extends Zend_Cache_Backend_File
{
    /**
     * Available options
     *
     * =====> (string) cache_dir :
     * - Directory where to put the cache files
     *
     * =====> (boolean) file_locking :
     * - Enable / disable file_locking
     * - Can avoid cache corruption under bad circumstances but it doesn't work on multithread
     * webservers and on NFS filesystems for example
     *
     * =====> (boolean) read_control :
     * - Enable / disable read control
     * - If enabled, a control key is embeded in cache file and this key is compared with the one
     * calculated after the reading.
     *
     * =====> (string) read_control_type :
     * - Type of read control (only if read control is enabled). Available values are :
     *   'md5' for a md5 hash control (best but slowest)
     *   'crc32' for a crc32 hash control (lightly less safe but faster, better choice)
     *   'adler32' for an adler32 hash control (excellent choice too, faster than crc32)
     *   'strlen' for a length only test (fastest)
     *
     * =====> (int) hashed_directory_level :
     * - Hashed directory level
     * - Set the hashed directory structure level. 0 means "no hashed directory
     * structure", 1 means "one level of directory", 2 means "two levels"...
     * This option can speed up the cache only when you have many thousands of
     * cache file. Only specific benchs can help you to choose the perfect value
     * for you. Maybe, 1 or 2 is a good start.
     *
     * =====> (int) hashed_directory_umask :
     * - Umask for hashed directory structure
     *
     * =====> (string) file_name_prefix :
     * - prefix for cache files
     * - be really carefull with this option because a too generic value in a system cache dir
     *   (like /tmp) can cause disasters when cleaning the cache
     *
     * =====> (int) cache_file_umask :
     * - Umask for cache files
     *
     * =====> (int) metatadatas_array_max_size :
     * - max size for the metadatas array (don't change this value unless you
     *   know what you are doing)
     *
     * @var array available options
     */
    protected $_options = array(
        'cache_dir' => null,
        'file_locking' => true,
        'read_control' => false,
        'read_control_type' => 'crc32',
        'hashed_directory_level' => 2,
        'hashed_directory_umask' => 0770,
        'file_name_prefix' => 'cm',
        'cache_file_umask' => 0660,
        'metadatas_array_max_size' => 100
    );

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        if ( ! isset($options['cache_dir']) || ! strlen($options['cache_dir']) && class_exists('Mage', false)) {
            $options['cache_dir'] = Mage::getBaseDir('cache');
        }
        parent::__construct($options);
    }

    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * @param string $id cache id
     * @param boolean $doNotTestCacheValidity if set to true, the cache validity won't be tested
     * @return string|false cached datas
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        $metadatas = $this->_getMetadatas($id);
        if ( ! $metadatas) {
            return false;
        }
        if ( ! $doNotTestCacheValidity && (time() > $metadatas['expire'])) {
            return false;
        }

        $data = $this->_fileGetContents($this->_file($id));
        if ($this->_options['read_control']) {
            $hashData = $this->_hash($data, $this->_options['read_control_type']);
            $hashControl = $metadatas['hash'];
            if ($hashData != $hashControl) {
                // Problem detected by the read control !
                $this->_log('Zend_Cache_Backend_File::load() / read_control : stored hash and computed hash do not match');
                $this->remove($id);
                return false;
            }
        }
        return $data;
    }

    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data             Datas to cache
     * @param  string $id               Cache id
     * @param  array  $tags             Array of strings, the cache record will be tagged by each string entry
     * @param  bool|int $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @return boolean true if no problem
     */
    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
        $res = parent::save($data, $id, $tags, $specificLifetime);
        $res = $res && $this->_writeIdTags($id, $tags);
        return $res;
    }

    /**
     * Remove a cache record
     *
     * @param  string $id cache id
     * @return boolean true if no problem
     */
    public function remove($id)
    {
        $file = $this->_file($id);
        $metadatas = $this->getMetadatas($id);
        if ($metadatas) {
            $boolRemove   = $this->_remove($file);
            $boolMetadata = $this->_delMetadatas($id);
            $boolTags     = $this->_removeIdTags($id, $metadatas['tags']);
            return $boolMetadata && $boolRemove && $boolTags;
        }
        return true;
    }

    /**
     * Clean some cache records
     *
     * Available modes are :
     * 'all' (default)  => remove all cache entries ($tags is not used)
     * 'old'            => remove too old cache entries ($tags is not used)
     * 'matchingTag'    => remove cache entries matching all given tags
     *                     ($tags can be an array of strings or a single string)
     * 'notMatchingTag' => remove cache entries not matching one of the given tags
     *                     ($tags can be an array of strings or a single string)
     * 'matchingAnyTag' => remove cache entries matching any given tags
     *                     ($tags can be an array of strings or a single string)
     *
     * @param string $mode
     * @param array $tags
     * @return boolean true if no problem
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        // We use this protected method to hide the recursive stuff
        clearstatcache();
        switch($mode) {
            case 'old':
                return $this->_clean($this->_options['cache_dir'], $mode, $tags);
            default:
                return $this->_cleanNew($mode, $tags);
        }
    }

    /**
     * Return an array of stored tags
     *
     * @return array array of stored tags (string)
     */
    public function getTags()
    {
        $prefix = $this->_tagFile('');
        $prefixLen = strlen($prefix);
        $tags = array();
        foreach (@glob($prefix . '*') as $tagFile) {
            $tags[] = substr($tagFile, $prefixLen);
        }
        return $tags;
    }

    /**
     * Return an array of stored cache ids which match given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of matching cache ids (string)
     */
    public function getIdsMatchingTags($tags = array())
    {
        return $this->_getIdsByTags(Zend_Cache::CLEANING_MODE_MATCHING_TAG, $tags);
    }

    /**
     * Return an array of stored cache ids which don't match given tags
     *
     * In case of multiple tags, a logical OR is made between tags
     *
     * @param array $tags array of tags
     * @return array array of not matching cache ids (string)
     */
    public function getIdsNotMatchingTags($tags = array())
    {
        return $this->_getIdsByTags(Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG, $tags);
    }

    /**
     * Return an array of stored cache ids which match any given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of any matching cache ids (string)
     */
    public function getIdsMatchingAnyTags($tags = array())
    {
        return $this->_getIdsByTags(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);
    }

    /**
     * Return an array of metadatas for the given cache id
     *
     * The array must include these keys :
     * - expire : the expire timestamp
     * - tags : a string array of tags
     * - mtime : timestamp of last modification time
     *
     * @param string $id cache id
     * @return array array of metadatas (false if the cache id is not found)
     */
    public function getMetadatas($id)
    {
        $metadatas = parent::getMetadatas($id);
        if ($metadatas && is_string($metadatas['tags'])) {
            $metadatas['tags'] = explode("\n",$metadatas['tags']);
        }
        return $metadatas;
    }

    /**
     * Get a metadatas record
     *
     * @param  string $id  Cache id
     * @return array|false Associative array of metadatas
     */
    protected function _getMetadatas($id)
    {
        return $this->_loadMetadatas($id);
    }

    /**
     * Set a metadatas record
     *
     * @param  string $id        Cache id
     * @param  array  $metadatas Associative array of metadatas
     * @param  boolean $save     optional pass false to disable saving to file
     * @return boolean True if no problem
     */
    protected function _setMetadatas($id, $metadatas, $save = true)
    {
        if ($save) {
            return $this->_saveMetadatas($id, $metadatas);
        }
        return true;
    }

    /**
     * Return the complete directory path of a filename (including hashedDirectoryStructure)
     *
     * Uses multiple letters for a single-level hash rather than multiple levels
     *
     * @param  string $id Cache id
     * @param  boolean $parts if true, returns array of directory parts instead of single string
     * @return string Complete directory path
     */
    protected function _path($id, $parts = false)
    {
        $partsArray = array();
        $root = $this->_options['cache_dir'];
        $prefix = $this->_options['file_name_prefix'];
        if ($this->_options['hashed_directory_level']>0) {
            $hash = hash('adler32', $id);
            $root = $root . $prefix . '--' . substr($hash, -$this->_options['hashed_directory_level']) . DIRECTORY_SEPERATOR;
            $partsArray[] = $root;
        }
        if ($parts) {
            return $partsArray;
        } else {
            return $root;
        }
    }

    /**
     * Save metadatas to disk
     *
     * @param  string $id        Cache id
     * @param  array  $metadatas Associative array
     * @return boolean True if no problem
     */
    protected function _saveMetadatas($id, $metadatas)
    {
        if ($metadatas['tags']) { // Rarely needs to be deserialized so optimize deserialization
            $metadatas['tags'] = implode("\n",$metadatas['tags']);
        }
        return parent::_saveMetadatas($id, $metadatas);
    }

    /**
     * Clean some cache records (protected method used for recursive stuff)
     *
     * Available modes are :
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @throws Zend_Cache_Exception
     * @return boolean True if no problem
     */
    protected function _cleanNew($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        $result = true;
        if ($mode == Zend_Cache::CLEANING_MODE_ALL) {
            $ids = $this->getIds();
            $tags = $this->getTags();
        }
        else {
            $ids = $this->_getIdsByTags($mode, $tags);
        }
        foreach ($ids as $id) {
            $idFile = $this->_file($id);
            if (is_file($idFile)) {
                $result = $result && $this->_remove($idFile) && $this->_delMetadatas($id);
            }
        }
        switch($mode)
        {
            case Zend_Cache::CLEANING_MODE_ALL:
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                foreach ($tags as $tag) {
                    $tagFile = $this->_tagFile($tag);
                    if (is_file($tagFile)) {
                        $result = $result && $this->_remove($tagFile);
                    }
                }
                break;
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                foreach ($tags as $tag) {
                    $tagIds = array_diff($this->_getTagIds($tag), $ids);
                    $this->_saveTagIds($tag, $tagIds);
                }
                break;
        }
        return $result;
    }

    /**
     * @param string $mode
     * @param array $tags
     * @return array
     */
    protected function _getIdsByTags($mode, $tags)
    {
        $ids = array();
        switch($mode) {
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                $ids = $this->getIds();
                if ($tags) {
                    foreach ($tags as $tag) {
                        if ( ! $ids) {
                            break; // early termination optimization
                        }
                        $ids = array_diff($ids, $this->_getTagIds($tag));
                    }
                }
                break;
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                if ($tags) {
                    $tag = array_shift($tags);
                    $ids = $this->_getTagIds($tag);
                    foreach ($tags as $tag) {
                        if ( ! $ids) {
                            break; // early termination optimization
                        }
                        $ids = array_intersect($ids, $this->_getTagIds($tag));
                    }
                }
                break;
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                foreach ($tags as $tag) {
                    $ids = array_merge($ids, $this->_getTagIds($tag));
                }
                $ids = array_unique($ids);
                break;
        }
        return $ids;
    }

    /**
     * Make and return a file name (with path)
     *
     * @param  string $id Cache id
     * @return string File name (with path)
     */
    protected function _tagFile($id)
    {
        $path = $this->_tagPath();
        $fileName = $this->_idToFileName($id);
        return $path . $fileName;
    }

    /**
     * Return the complete directory path where tags are stored
     *
     * @return string Complete directory path
     */
    protected function _tagPath()
    {
        $path = $this->_options['cache_dir'] . DIRECTORY_SEPARATOR . 'tags' . DIRECTORY_SEPARATOR;
        if ( ! $this->isTagDir) {
            if ( ! is_dir($path)) {
                @mkdir($path, $this->_options['hashed_directory_umask']);
                @chmod($path, $this->_options['hashed_directory_umask']); // see #ZF-320 (this line is required in some configurations)
            }
            $this->isTagDir = true;
        }
        return $path;
    }

    /**
     * @param string $tag
     * @return array
     */
    protected function _getTagIds($tag)
    {
        $file = $this->_tagFile($tag);
        if($ids = $this->_fileGetContents($file)) {
            return array_unique((array)explode("\n", rtrim($ids,"\n")));
        }
        return array();
    }

    /**
     * @param string $tag
     * @param array $ids
     * @return bool
     */
    protected function _saveTagIds($tag, $ids)
    {
        $file = $this->_tagFile($tag);
        if ($ids) {
            return $this->_filePutContents($file, implode("\n", array_unique($ids))."\n");
        } else if(is_file($file)) {
            return $this->_remove($file);
        }
        return true;
    }

    /**
     * @param string $tag
     * @param array $ids
     * @return bool
     */
    protected function _appendTagIds($tag, $ids)
    {
        $file = $this->_tagFile($tag);
        if ($ids) {
            return $this->_fileAppendContents($file, implode("\n", $ids)."\n");
        } else if(is_file($file)) {
            return $this->_remove($file);
        }
        return true;
    }

    /**
     * @param string $id
     * @param array $tags
     * @return bool
     */
    protected function _saveIdTags($id, $tags)
    {
        $result = true;
        foreach($tags as $tag) {
            $ids = $this->_getTagIds($tag);
            $ids[] = $id;
            $result = $this->_saveTagIds($tag, $ids) && $result;
        }
        return $result;
    }

    /**
     * @param string $id
     * @param array $tags
     * @return bool
     */
    protected function _writeIdTags($id, $tags)
    {
        $result = true;
        foreach($tags as $tag) {
            $file = $this->_tagFile($tag);
            if (rand(1,100) == 1 && @filesize($file) > 4096) {
                $result = $this->_saveIdTags($id, array($tag));
            } else {
                $result = $this->_appendTagIds($tag, array($id)) && $result;
            }
        }
        return $result;
    }

    /**
     * @param string $id
     * @param array $tags
     * @return bool
     */
    protected function _removeIdTags($id, $tags)
    {
        $result = true;
        foreach($tags as $tag) {
            $ids = array_diff($this->_getTagIds($tag), array($id));
            $result = $this->_saveTagIds($tag, $ids) && $result;
        }
        return $result;
    }

  /**
   * Put the given string into the given file
   *
   * @param  string $file   File complete path
   * @param  string $string String to put in file
   * @return boolean true if no problem
   */
  protected function _fileAppendContents($file, $string)
  {
      $result = false;
      $f = @fopen($file, 'ab');
      if ($f) {
          if ($this->_options['file_locking']) @flock($f, LOCK_EX);
          $tmp = @fwrite($f, $string);
          if (!($tmp === FALSE)) {
              $result = true;
          }
          @fclose($f);
      }
      @chmod($file, $this->_options['cache_file_umask']);
      return $result;
  }

}
