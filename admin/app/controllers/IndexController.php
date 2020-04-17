<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Magneto\Plugins\Weixin;
use Group\Magneto\Admin\Daos\MonitorDAO;

class IndexController extends BaseController
{

    public function indexAction()
    {
        $views = MonitorDAO::getAllViewConfig();
        $views = array_reverse($views);
        $this->view->setVar('views', $views);
    }

}
