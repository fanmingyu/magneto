<?php
/* Code: */

use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Group\Common\Extensions\Varz\VarzAdapter;
use Group\Common\Library\TraceSdk;

mb_internal_encoding("UTF-8");

// register global class-dirs, class-namespace and class-prefix
// $loader->registerDirs(array())->register();

$loader->registerNamespaces($config->namespace->toArray())->register();

//$loader->registerPrefixes(
//    array(
//        "JsonMapper_" => $system . "/Common/Vendor/jsonmapper/src/JsonMapper/",
//    ))->register();

// class autoloader
$di->setShared('loader', function () use ($loader) {
    return $loader;
});

// global config
$di->set('config', function () use ($config) {
    return $config;
});

// global logger
$di->set('logger', function () use ($config) {
    try {
        $logger = new FileAdapter($config->application->logFilePath);
        return $logger;
    } catch (\Exception $e) {
        throw $e;
    }
}, true);

// redis service
$di->setShared('redis', function () use ($config) {
    try {
        $redis = new Redis();
        $redis->connect($config->redis->host, $config->redis->port);
        return $redis;
    } catch (\Exception $e) {
        throw $e;
    }
});

$di->setShared('modelsManager', function() {
    return new \Phalcon\Mvc\Model\Manager();
});

// global funciton to retrive $di
if (!function_exists("getDI")) {
    function getDI()
    {
        return \Phalcon\DI::getDefault();
    }
}

if (!isset($config['trace']) || !$config['trace']['enableTrace']) {
    TraceSdk::disable();
} else {
    if (isset($config['trace']['logLevel'])) {
        TraceSdk::setLogLevel($config['trace']['logLevel']);
    }

    TraceSdk::init($projectName);
}

/* default.php ends here */
