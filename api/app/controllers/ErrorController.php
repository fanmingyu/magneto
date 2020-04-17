<?php

namespace Group\Magneto\Admin\Controllers;

class ErrorController extends \Phalcon\Mvc\Controller
{

    public function show404Action()
    {
        exit('Not found');
    }

}
