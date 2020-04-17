<?php

define('APP_START_TIME', microtime(true));

require dirname(dirname(__DIR__)).'/Common/Phalcon/Bootstrap.php';
$bootstrap = new \Group\Common\Phalcon\Bootstrap(dirname(__DIR__));
$bootstrap->exec();
