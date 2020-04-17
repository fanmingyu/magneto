<?php

define('WEIXIN_API', true);

require dirname(dirname(__DIR__)).'/Common/Phalcon/Bootstrap.php';
$bootstrap = new \Group\Common\Phalcon\Bootstrap(dirname(dirname(__DIR__)).'/admin');
$bootstrap->exec();
