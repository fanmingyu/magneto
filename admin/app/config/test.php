<?php

return array(
    "view" => array(
        "compiledPath" => $system . '/cache/view/',
        "compiledExtension" => ".compiled",
    ),
    'application' => array(
        "name" => "web",
        "namespace" => "Group\\Magneto\Admin\\",
        "mockRpc" => false,
        "mode" => "Web",
        'staticUri' => '/',
    ),
    'logger' => array(
        'file' => array(
            'path' => $system.'/logs/magneto_'.date('Ymd').'.log',
        ),
    ),
    'dbs' => array(
        'magneto' => array(
            'adapter' => 'Mysql',
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => 'bc91c3985bf2299a3f1535c9e5c2798b)D',
            'dbname' => 'magneto',
            'port' => '3306',
        ),
    ),

    //redis 可配多节点哨兵
    'redis' => array(
//        array(
//            'type' => 'single', //single:单点高可用; sentinels:哨兵
//            'host' => '39.96.66.39',
//            'port' => '26380',
//            'password' => 'test.123',
//        ),
        array(
            'type' => 'single', //single:单点高可用; sentinels:哨兵
            'host' => 'jurentest.redis.rds.aliyuncs.com',
            'port' => '6379',
            'password' => 'JURENredis123',
        ),
    ),

    'capture' => array(
        'host' => 'http://127.0.0.1:14000/web/capture',
    ),
);
