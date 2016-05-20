<?php

namespace Ankalagon\KeepAChangeLog;

class MarkdownDecorator implements DecoratorInterface
{
    /**
     * Prepared output
     * @var string
     */
    private $_output = '';

    /**
     * Version on production
     * @var string
     */
    private $_version = '';

    /**
     * Return full rendered changelog
     * @param Changelog $changelog
     * @return string
     */
    public function render(Changelog $changelog)
    {
        $this->_output = '';

        $this->_version = $changelog->getVersion();

        $rawData = $changelog->getRawData();

        foreach ($rawData as $tag => $data) {
            $this->_output .= $this->_render($data['groups'], $data['time'], $tag);
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
        if ($tag == 'HEAD') {
            if ($groups == false) {
                return '';
            }
            $str .= sprintf('## [%s]', 'Unreleased').PHP_EOL;
        } elseif ($tag == $this->_version) {
            $str .= sprintf('## [%s] - %s **ON PRODUCTION**', $tag, $date).PHP_EOL;
        } else {
            $str .= sprintf('## [%s] - %s', $tag, $date).PHP_EOL;
        }

        foreach ($groups as $group => $messages) {
            $str .= sprintf('### %s', $group).PHP_EOL;
            foreach ($messages as $message) {
                $str .= sprintf('- %s', $message['message']).PHP_EOL;
            }
            $str .= PHP_EOL;
        }

        $str .= PHP_EOL;

        return $str;
    }
}
