<?php
require $system."/Common/load/default.php";

$di->set('router', function() {
        $router = new \Phalcon\CLI\Router();
        return $router;
});

$di->set('dispatcher', function() use ($di) {
        $dispatcher = new Phalcon\CLI\Dispatcher();
        $dispatcher->setDI($di);
        return $dispatcher;
});

/* default-cli.php ends here */
