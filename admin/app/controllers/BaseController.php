<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Common\Library\HttpLib;
use Group\Common\Library\Logger;

class BaseController extends \Phalcon\Mvc\Controller
{

    /**
     * 角色
     */
    protected $roles = array(
        'message' => '仅接收消息',
        'test' => '测试',
        'dev' => '开发',
        'admin' => '管理员',
    );

    /**
     * 固定用户角色，优先级大于数据库保存的设置
     */
    private $userRoleFixed = array(
        'admin@juren.com' => 'admin',
    );

    /**
     * 无需验证的页面或接口
     */
    private $withoutAuth = array(
        'monitor/chart',
        'monitorview/capture',
    );

    /**
     * 角色对应的权限
     */
    private $rolePermission = array(
        'base' => array(
            'index/*',
            'sso/*',
            'error/*',
            'user/editself',
            'user/saveself',
        ),
        'admin' => array(
            '*',
        ),
        'dev' => array(
            'alarm/*',
            'monitor/*',
            'monitorview/*',
        ),
        'test' => array(
            'alarm/*',
            'monitor/*',
            'monitorview/*',
        ),
        'message' => array(
        ),
    );

    public function initialize()
    {
        if (isset($_GET['debug']) || true) {
            ini_set('display_errors', 'On');
            error_reporting(E_ALL);
        }

        if (isset($_GET['fullscreen'])) {
            $this->view->setVar('fullscreen', true);
        }


        // 是否登录
        session_start();
        $loginUserInfo = array();
        $email = '';
        $_SESSION['username'] = 'admin@juren.com'; $_SESSION['status'] = 1;
        if (!empty($_SESSION['status']) && isset($_SESSION['username'])) {
            $email = addslashes(trim($_SESSION['username']));
            $loginUserInfo = getDI()->get('db_magneto')->fetchOne("SELECT * FROM user WHERE email='{$email}'", \Phalcon\Db::FETCH_ASSOC);
        }
        session_write_close();

        $controller = $this->dispatcher->getControllerName();
        $action = $this->dispatcher->getActionName();
        if (!$this->hasPermission($email, $loginUserInfo['role'], $controller, $action)) {
            $this->error('没有该功能的访问权限', '', 1000000);
        }

        Logger::info("magneto access. email:{$email}, url:{$_SERVER['REQUEST_URI']}");

        $this->view->setVar('loginUserInfo', $loginUserInfo);
        $this->view->setVar('controller', $controller);
        $this->view->setVar('action', $action);
    }

    private function hasPermission($email, $role, $controller, $action)
    {
        // 免登录逻辑
        if (in_array("{$controller}/{$action}", $this->withoutAuth)) {
            return true;
        }

        // 是否登录
        if (empty($email)) {
            HttpLib::redirect('/sso/login?url='.urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']));
            exit();
        }

        // 是否有该页面的权限
        if (isset($this->userRoleFixed[$email])) {
            $role = $this->userRoleFixed[$email];
        }
        $permission = array_flip(array_merge($this->rolePermission[$role], $this->rolePermission['base']));
        if (!isset($permission['*']) && !isset($permission["{$controller}/*"]) && !isset($permission["{$controller}/{$action}"])) {
            return false;
        }

        return true;
    }

    protected function success($url = '', $message = '操作成功', $time = 500)
    {
        if ($url === '') {
            $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
        }

        $this->view->setVar('message', $message);
        $this->view->setVar('url', $url);
        $this->view->setVar('time', $time);

        $this->view->render('common', 'success');
        exit();
    }

    protected function error($message = '操作失败', $url = '', $time = 5000)
    {
        if ($url === '') {
            $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
        }

        $this->view->setVar('message', $message);
        $this->view->setVar('url', $url);
        $this->view->setVar('time', $time);

        $this->view->render('common', 'error');
        exit();
    }

    /**
     * 跳转
     */
    protected function redirect($url)
    {
        header("location:{$url}");
        exit();
    }

    protected function ajax($errno, $error, $data = []){
        $data = [
            'errno' => $errno,
            'error' => $error,
            'data' => $data
        ];
        header('Content-type: application/json;charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        return;
    }

}
