<?php

namespace Changelog;

class Changelog
{
    /**
     * Pattern to recognize tags
     * @var string
     */
    private $_tagPattern = '';

    /**
     * Available groups in changelog
     * @var array
     */
    private $_availableGroups = array(
        'Added', // for new features.
        'Changed', // for changes in existing functionality.
        'Deprecated', // for once-stable features removed in upcoming releases.
        'Removed', // for deprecated features removed in this release.
        'Fixed', // for any bug fixes.
        'Security' // to invite users to upgrade in case of vulnerabilities'
    );

    /**
     * Recognized groups in log
     * @var array
     */
    private $_groups = array();

    /**
     * Create Changelog Object and set path to git reporitory
     * @param string $repository_path Path to repository
     */
    public function __construct($repository_path)
    {
        $this->_git = new \PHPGit\Git();
        $this->_git->setRepository($repository_path);
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

                echo $this->_render(
                    $this->_getGroups($log),
                    $this->_getTime($log),
                    $newTag
                );
            }

            $newTag = $tag;
        }
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
        $str .= sprintf('## [%s] - %s', $tag, $date).PHP_EOL;

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

        return date('Y-m-d', $date);
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

        $this->_tags = array_reverse($this->_tags);

        return $this->_tags;
    }

    /**
     * Recognize one of available groups in changelog message, default message logs to Changed group
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

        return 'Changed'; //default value to return
    }
}