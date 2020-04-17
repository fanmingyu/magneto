<?php
require $system."/loads/default.php";

$di->set('router', function() {
    $router = new \Phalcon\Mvc\Router();
    return $router;
});

$di->set('request', function() {
    return new Phalcon\Http\Request();
});

$application->get('/default', function() use ($application)  {
    echo "Hello, BullSoft Micro APP!!!";
});