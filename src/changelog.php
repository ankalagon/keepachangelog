<?php

namespace Changelog;

class Changelog
{
    /**
     * Pattern to recognize tags
     * @var string
     */
    private $_tagPattern = '[v]?[0-9]{1}.[0-9]{1}.?[0-9]{0,3}';

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
     * Default Group
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
     * Buffered output
     * @var bool
     */
    private $_output = false;

    /**
     * Date format for release date
     * @var string
     */
    private $_dateFormat = 'Y-m-d';

    /**
     * const
     */
    const HEAD = 'HEAD';

    /**
     * Create Changelog Object and set path to git reporitory
     * @param string $repository_path Path to repository
     */
    public function __construct($repository_path)
    {
        $this->_git = new \PHPGit\Git();
        $this->_git->setRepository($repository_path);
        $this->setPrefixFor($this->_defaultGroupsPrefixes);
    }

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

    public function setDateFormatPattern($pattern)
    {
        $this->_dateFormat = $pattern;
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

    public function generate()
    {
        $newTag = '';
        foreach ($this->_getTags() as $tag) {
            if (false == $newTag) {
                $newTag = $tag;
                continue;
            }

            if ($tag != $newTag) {
                $log = $this->_git->log($tag.'..'.$newTag, '', array('limit' => 1000));

                $this->_output .= $this->_render(
                    $this->_getGroups($log),
                    $this->_getTime($log),
                    $newTag
                );
            }

            $newTag = $tag;
        }

        return $this->_output;
    }


    /**
     * Returns one log result
     * @param  Array $groups  Recognized groups
     * @param  String $date   Creation date of tag (ie. 2015-07-12)
     * @param  String $tag    Tag number (ie. 1.2.1)
     * @return String         Complete log message (for one tag)
     */
    private function _render(Array $groups, $date, $tag)
    {
        $str = '';

        if ($tag == self::HEAD) {
            $str .= sprintf('## [%s]', 'Unreleased').PHP_EOL;
        } else {
            $str .= sprintf('## [%s] - %s', $tag, $date).PHP_EOL;
        }

        foreach ($groups as $group => $messages) {
            $str .= sprintf('### %s', $group).PHP_EOL;
            foreach ($messages as $message) {
                $str .= sprintf('- %s', $message).PHP_EOL;
            }
            $str .= PHP_EOL;
        }

        $str .= PHP_EOL;

        return $str;
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
    private function _getTags()
    {
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
            $data[$group][] = ucfirst($message['title']);
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


