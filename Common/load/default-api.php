<?php

require $system."/Common/load/default.php";

$di->set('router', function() {
    $router = new \Phalcon\Mvc\Router();
    return $router;
});

$di->set('request', function() {
    return new \Phalcon\Http\Request();
});

/**
 * If our request contains a body, it has to be valid JSON.  This parses the
 * body into a standard Object and makes that vailable from the DI.  If this service
 * is called from a function, and the request body is nto valid JSON or is empty,
 * the program will throw an Exception.
 */
$di->setShared('requestBody', function() {
    $in = file_get_contents('php://input');
    $in = json_decode($in, true);
    // JSON body could not be parsed, throw exception
    if ($in === null) {
        throw new \Group\Common\Extensions\Exceptions\RpcApiException (
            'There was a problem understanding the data sent to the server by the application.',
            409,
            array(
                'dev' => 'The JSON body sent to the server was unable to be parsed.',
                'internalCode' => 'REQ1000',
                'more' => ''
            )
        );
    }
    return $in;
});