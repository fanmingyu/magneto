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
            'host' => '172.17.112.199',
            'username' => 'jr_magneto',
            'password' => 'jd93swHda93d.ad8dj3w03Gp72SD88',
            'dbname' => 'magneto',
            'port' => '3306',
        ),
    ),

    //redis 可配多节点哨兵
    'redis' => array(
        array(
            'type' => 'single', //single:单点高可用; sentinels:哨兵
            'host' => 'redis.app.juren.com.cn',
            'port' => '6379',
            'password' => 'blackwidow:Be34186335b',
            //'host' => '172.17.22.65',
            //'port' => '26379',
            //'password' => 'juren.123',
        ),
    ),

    'capture' => array(
        'host' => 'http://127.0.0.1:14000/web/capture',
    ),
);
