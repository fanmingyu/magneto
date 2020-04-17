<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Magneto\Plugins\Weixin;

class WeixinController extends BaseController
{

    public function indexAction()
    {
        $result = Weixin::instance('contacts')->getAppList();
        $this->view->setVar('result', $result['agentlist']);
    }

    public function departmentAction()
    {
        $department = Weixin::instance('contacts')->getDepartmentList();
        $this->view->setVar('result', $department);
    }

}
