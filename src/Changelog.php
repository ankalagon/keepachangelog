<?php

namespace Ankalagon\KeepAChangeLog;

class Changelog
{
    /**
     * Pattern to recognize tags
     * @var string
     */
    private $_tagPattern = '[v]?[0-9]{1,3}.[0-9]{1,3}.?[0-9]{0,3}';

    /**
     * Available groups in changelog
     * @var array
     */
    private $_availableGroups = array(
        'Changed', // for changes in existing functionality.
        'Added', // for new features.
        'Deprecated', // for once-stable features removed in upcoming releases.
        'Removed', // for deprecated features removed in this release.
        'Fixed', // for any bug fixes.
        'Security', // to invite users to upgrade in case of vulnerabilities
        'Merged', // for Merge requests
    );

    /**
     * Default phrases in groups
     * @var array
     */
    private $_defaultGroupsPrefixes = array(
        'Added' => array(
            'Add',
            'Added'
        ),
        'Deprecated' => array(
            'Depracated'
        ),
        'Removed' => array(
            'Remove',
            'Deleted'
        ),
        'Fixed' => array(
            'Fix',
            'Hotfix',
            'Bug',
            'Quickfix'
        ),
        'Merged' => array(
            'Merge'
        )
    );

    /**
     * Default Group, used when any other groups are unrecognised
     */
    const defaultGroup = 'Changed';

    /**
     * Recognized groups in log
     * @var array
     */
    private $_groups = array();

    /**
     * If generate unreleased
     * @var bool
     */
    private $_generateUnreleased = false;

    /**
     * result of processing diffs
     * @var array
     */
    private $_result = array();

    /**
     * raw result from git log command
     * @var array
     */
    private $_rawResult = array();

    /**
     * Date format for release date
     * @var string
     */
    private $_dateFormat = 'Y-m-d';

    /**
     * Version on production
     * @var string
     */
    private $_version = '';

    /**
     * If we should create changelog from beginning (first tag)
     * @var bool
     */
    private $_fromBeginning = true;

    /**
     * HEAD of repository
     * const
     */
    const HEAD = 'HEAD';

    /**
     * dir in with cache is loaded
     * @var string
     */
    private $_cacheDir = '/tmp/';

    /**
     * Cache data
     * @var array
     */
    private $_cacheData = [];

    /**
     * path to repository
     * @var string
     */
    private $_repository = '';

    /**
     * Create Changelog Object and set path to git reporitory
     * @param string $repository_path Path to repository
     */
    public function __construct($repository_path)
    {
        $this->_repository = $repository_path;

        $this->_git = new \PHPGit\Git();
        $this->_git->setRepository($this->_repository);
        $this->setPrefixFor($this->_defaultGroupsPrefixes);
        $this->_loadCache();
    }

    private function _loadCache()
    {
        $cacheFile = md5($this->_repository);
        if (is_file($this->_cacheDir.$cacheFile )) {
            $tmp = file_get_contents($this->_cacheDir.$cacheFile);
            $this->_cacheData = json_decode($tmp, true);
        }
    }

    private function _saveCache($data)
    {
        $cacheFile = md5($this->_repository);
        file_put_contents($this->_cacheDir.$cacheFile, json_encode($data));
    }

    /**
     * To generate or nor UNRELEASED section
     * @param bool|true $generate
     */
    public function setGenerateUnreleased($generate = true)
    {
        $this->_generateUnreleased = (bool) $generate;
    }

    /**
     * Set patterns for tags
     * @param string $pattern pattern for tags to find (ie: 2.[0-9]{1,2}.[0-9]{1,3})
     */
    public function setTagPattern($pattern)
    {
        $this->_tagPattern = $pattern;
    }

    /**
     * To change the output date format
     * @param $pattern
     */
    public function setDateFormatPattern($pattern)
    {
        $this->_dateFormat = $pattern;
    }

    /**
     * To set version on production
     * @param $version
     */
    public function setVersion($version)
    {
        $this->_version = trim($version);
    }

    /**
     * Get version on production
     * @return string
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * Set prefix for changes according to: http://keepachangelog.com/ (CASE SENSITIVE IS IMPORTANT)
     * @param Array $prefix
     */
    public function setPrefixFor(Array $prefixes)
    {
        foreach ($this->_availableGroups as $group) {
            if (isset($prefixes[$group])) {
                if (!is_array($prefixes[$group])) {
                    $prefixes[$group] = array($prefixes[$group]);
                }

                foreach ($prefixes[$group] as $pattern) {
                    $this->_groups[$group][] = $pattern;
                }
            }
        }
    }

    public function getRawData()
    {
        $newTag = '';
        foreach ($this->_getTags() as $tag) {
            if (false == $newTag) {
                $newTag = $tag;
                continue;
            }

            if ($tag != $newTag) {
                $log = $this->_getDiff($tag, $newTag);

                $this->_rawResult[$newTag] = $log;
                $this->_result[$newTag] = array(
                    'groups' => $this->_getGroups($log),
                    'time' => $this->_getTime($log)
                );
            }

            $newTag = $tag;
        }

        $this->_saveCache($this->_rawResult);
        return $this->_result;
    }

    private function _getDiff($tag, $newTag)
    {
        if (isset($this->_cacheData[$newTag])) {
            return $this->_cacheData[$newTag];
        }

        return $this->_git->log($tag.'..'.$newTag, '', array('limit' => 1000));
    }

    /**
     * Recognize creation time of tag
     * @param  Array $logs  git log message
     * @return String       Time of tag creation
     */
    private function _getTime(Array $logs)
    {
        $date = 0;
        foreach ($logs as $log) {
            $tmpDate = strtotime($log['date']);
            if ($tmpDate > $date) {
                $date = $tmpDate;
            }
        }

        return date($this->_dateFormat, $date);
    }



    /**
     * Gets all tags according to pattern and sorts in natural order
     * @return Array  tags
     */
    public function _getTags()
    {
        $this->_tags = [];
        $tags = $this->_git->tag();
        foreach ($tags as $tag) {
            if (preg_match('/^'.$this->_tagPattern.'/', $tag)) {
                $this->_tags[] = $tag;
            }
        }

        if (false == natsort($this->_tags)) {
            throw new \Exception('Tag sorting error - invalid sorting');
        }

        if ($this->_generateUnreleased) {
            $this->_tags[] = 'HEAD';
        }

        $this->_tags = array_reverse($this->_tags);

        return $this->_tags;
    }

    /**
     * Recognize one of available groups in changelog message, default message logs to "Changed" group
     * @param  Array  $log Log message for tag
     * @return Array      Array of recognized groups
     */
    private function _getGroups(Array $log)
    {
        $data = array();
        foreach ($log as $message) {
            if (!isset($message['title'])) {
                throw new \RuntimeException('Log message hasn\'t got `title` field');
            }

            $group = $this->_recognizeGroup($message['title']);
            $data[$group][] = [
                'message' => ucfirst($message['title']),
                'user' => $message['name'],
                'email' => $message['email']
            ];
        }

        foreach ($data as $key => $value) {
            asort($data[$key]);
            $data[$key] = array_unique($data[$key]);
        }

        return $data;
    }

    /**
     * Recognize group from single message
     * @param  String $message Log message
     * @return String          One of available groups
     */
    private function _recognizeGroup($message)
    {
        foreach ($this->_groups as $group => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos(trim($message), $pattern) === 0) {
                    return $group;
                }
            }
        }

        return self::defaultGroup;
    }
}
