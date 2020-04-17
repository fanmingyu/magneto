<?php
ini_set("memory_limit", "4G");
require dirname(dirname(dirname(__DIR__))) . '/Common/Phalcon/Bootstrap.php';
$bootstrap = new \Group\Common\Phalcon\Bootstrap(dirname(dirname(__DIR__)));
$di = new \Phalcon\DI\FactoryDefault\CLI();

$loader = new \Phalcon\Loader();
$loader->registerDirs(array(__DIR__))
       ->register();

$arguments = array();
foreach($argv as $k => $arg) {
    if($k == 1) {
        $arguments['task'] = $arg;
    } elseif($k == 2) {
        $arguments['action'] = $arg;
    } elseif($k >= 3) {
        $arguments['params'][] = $arg;
    }
}

try {
    $bootstrap->execTask($arguments, $di);
}
catch (\Exception $e) {
    echo $e->getMessage();
    exit(255);
}
