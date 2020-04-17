<?php

namespace Group\Magneto\Admin\Controllers;

class ErrorController extends BaseController
{

    public function show404Action()
    {
        $this->error('Not found');
    }

}
