<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Magneto\Plugins\Weixin;
use Group\Magneto\Admin\Service\UserService;

class UserController extends BaseController
{

    public function initialize()
    {
        parent::initialize();
        $this->view->setVar('fullscreen', true);
    }

    public function indexAction()
    {
        $userInfo = array();
        $result = getDI()->get('db_magneto')->fetchAll("SELECT * FROM user", \Phalcon\Db::FETCH_ASSOC);
        foreach ($result as $value) {
            $userInfo[$value['email']] = $value;
        }

        $weixinUser = array();
        $userOnlyInWeixin = array();
        $result = Weixin::instance('contacts')->getUserList();
        foreach ($result['userlist'] as $item) {
            $email = $item['userid'].'@juren.com';
            $weixinUser[$email] = $item;

            if (empty($userInfo[$email])) {
                $userOnlyInWeixin[] = $item;
            }
        }

        $department = Weixin::instance('contacts')->getDepartmentList();

        $corpWeixinUser = array();
        $result = Weixin::instance('corp-alarm')->getUserList();
        foreach ($result['userlist'] as $item) {
            $email = strtolower($item['userid']).'@juren.com';
            $corpWeixinUser[$email] = $item;
        }

        $this->view->setVar('weixinUser', $weixinUser);
        $this->view->setVar('weixinDepartment', $department);
        $this->view->setVar('corpWeixinUser', $corpWeixinUser);
        $this->view->setVar('userInfo', $userInfo);
        $this->view->setVar('userOnlyInWeixin', $userOnlyInWeixin);
    }

    /**
     * 添加用户
     */
    public function addAction()
    {
        $department = Weixin::instance('contacts')->getDepartmentList();
        $weixinUserInfo = array('department' => array(Weixin::DEPARTMENT_ID));

        $this->view->setVar('roles', $this->roles);
        $this->view->setVar('department', $department);
        $this->view->setVar('weixinUserInfo', $weixinUserInfo);
        $this->view->setVar('userInfo', array('email' => '', 'mobile' => '', 'role' => ''));
    }

    /**
     * 修改用户信息
     */
    public function editAction()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $userInfo = getDI()->get('db_magneto')->fetchOne("SELECT * FROM user WHERE id='{$id}'", \Phalcon\Db::FETCH_ASSOC);
        if (empty($userInfo)) {
            $this->error('用户不存在');
        }

        $department = Weixin::instance('contacts')->getDepartmentList();

        $username = substr($userInfo['email'], 0, strpos($userInfo['email'], '@'));
        $weixinUserInfo = Weixin::instance('contacts')->getUser($username);

        $this->view->setVar('roles', $this->roles);
        $this->view->setVar('userInfo', $userInfo);
        $this->view->setVar('weixinUserInfo', $weixinUserInfo);
        $this->view->setVar('department', $department);
    }

    /**
     * 保存修改
     */
    public function saveAction()
    {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $email = isset($_POST['email']) ? addslashes(trim($_POST['email'])) : '';
        $mobile = isset($_POST['mobile']) ? addslashes(trim($_POST['mobile'])) : '';
        $role = isset($_POST['role']) ? addslashes(trim($_POST['role'])) : '';
        $departmentId = isset($_POST['departmentId']) ? intval($_POST['departmentId']) : 0;

        if (empty($email)) {
            $this->error('参数错误');
        }

        $userInfo = getDI()->get('db_magneto')->fetchOne("SELECT * FROM user WHERE email='{$email}' AND id!='{$id}'");
        if (!empty($userInfo)) {
            $this->error('用户已存在');
        }

        $data = array(
            'email' => $email,
            'mobile' => $mobile,
            'role' => $role,
        );

        $userService = new UserService();
        //添加用户
        if ($id === 0) {
            $data['createtime'] = time();
            $userService->create($data);
            $userService->syncWeixinUser($email, $mobile, $departmentId);
            $this->success('/user');
        }

        //修改用户
        $data['updatetime'] = time();
        $userService->update($id, $data);
        $userService->syncWeixinUser($email, $mobile, $departmentId);

        $this->success('/user');
    }

    /**
     * 修改自己的用户信息
     */
    public function editSelfAction()
    {
        $email = addslashes(trim($_SESSION['username']));
        $userInfo = getDI()->get('db_magneto')->fetchOne("SELECT * FROM user WHERE email='{$email}'", \Phalcon\Db::FETCH_ASSOC);

        $userId = substr($email, 0, strpos($email, '@'));
        $weixinUserInfo = Weixin::instance('contacts')->getUser($userId);

        $this->view->setVar('email', $email);
        $this->view->setVar('userInfo', $userInfo);
        $this->view->setVar('weixinUserInfo', $weixinUserInfo);
    }

    /**
     * 保存修改
     */
    public function saveSelfAction()
    {
        $data = array(
            'email' => addslashes(trim($_SESSION['username'])),
            'mobile' => addslashes(trim($_POST['mobile'])),
        );

        $userInfo = getDI()->get('db_magneto')->fetchOne("SELECT * FROM user WHERE email='{$data['email']}'");

        //添加
        if (empty($userInfo)) {
            $data['createtime'] = time();
            if (!getDI()->get('db_magneto')->insert('user', $data, array_keys($data))) {
                $this->error('添加失败');
            }
            $this->syncWeixinUser($data['email'], $data['mobile']);
            $this->success('/user/editself');
        }

        //修改
        $data['updatetime'] = time();
        if (!getDI()->get('db_magneto')->update('user', array_keys($data), array_values($data), "email='{$data['email']}'")) {
            $this->error('修改失败');
        }

        $this->syncWeixinUser($data['email'], $data['mobile']);
        $this->success('/user/editself');
    }

    /**
     * 删除账号
     */
    public function deleteAction()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        getDI()->get('db_magneto')->delete('user', "id='{$id}'");
        $this->success('/user');
    }

    /**
     * 同步到微信公共号
     */
    private function syncWeixinUser($email, $mobile, $departmentId = 0)
    {
        $username = substr($email, 0, strpos($email, '@'));

        $userInfo = Weixin::instance('contacts')->getUser($username);
        if (empty($userInfo['userid'])) {
            return Weixin::instance('contacts')->createUser($username, $username, $mobile, $departmentId);
        }

        return Weixin::instance('contacts')->updateUser($username, $username, $mobile, $departmentId);
    }

}
