<?php

if (php_sapi_name() != 'cli') {
    die("This example script can be used only in cli mode. If You want to use it in Your browser feel free to modify it.".PHP_EOL);
}

function includeIfExists($file) {
    if (file_exists($file)) {
        return include $file;
    }
}

if ((!$loader = includeIfExists(__DIR__."/../vendor/autoload.php")) && (!$loader = includeIfExists(__DIR__."/../../../autoload.php"))) {
    die("You must set up the project dependencies, run the following commands:".PHP_EOL.
        "curl -s http://getcomposer.org/installer | php".PHP_EOL.
        "php composer.phar install".PHP_EOL);
}


if (!isset($argv[1])) {
    die("Usage:".PHP_EOL.
        "php ".$argv[0]." /path/to/your/git/repository".PHP_EOL);
}

use Ankalagon\KeepAChangeLog\Changelog;
use Ankalagon\KeepAChangeLog\MarkdownDecorator;

try {

    $changelog = new Changelog($argv[1]);
//    $changelog->setGenerateUnreleased();
//    $changelog->setDateFormatPattern('\R\e\l\e\a\s\e\d Y-m-d');
//    $changelog->setPrefixFor(array(
//         'Fixed' => array(
//              'fix',
//               'hotfix',
//                'poprawka',
//                 'Bug',
//                  'Quickfix'
//               ),
//               'Removed' => 'Remove',
//        	    'Added' => 'add'
//    ));

    $decorator = new MarkdownDecorator();
    echo $decorator ->render($changelog);

} catch (Exception $e) {
    echo PHP_EOL.$e->getMessage().PHP_EOL;
}
