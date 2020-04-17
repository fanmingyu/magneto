<?php

namespace Group\Magneto\Admin\Service;

use Group\Common\Library\Logger;
use Group\Magneto\Plugins\Weixin;

class UserService
{

    /**
     * 创建用户
     */
    public function create($data)
    {
        return getDI()->get('db_magneto')->insert('user', $data, array_keys($data));
    }

    /**
     * 修改用户信息
     */
    public function update($id, $data)
    {
        return getDI()->get('db_magneto')->update('user', array_keys($data), array_values($data), "id='{$id}'");
    }

    /**
     * 同步到微信公共号
     */
    public function syncWeixinUser($email, $mobile, $departmentId = 0)
    {
        Logger::info($email);
        if (get_cfg_var('phalcon.env') !== 'product') {
            return true;
        }

        $username = substr($email, 0, strpos($email, '@'));
        $userInfo = Weixin::instance('contacts')->getUser($username);
        Logger::info($userInfo);
        if (empty($userInfo['userid'])) {
            $result = Weixin::instance('contacts')->createUser($username, $username, $mobile, $departmentId);
        } else {
            $result = Weixin::instance('contacts')->updateUser($username, $username, $mobile, $departmentId);
        }
        Logger::info($result);
        return $result['errcode'] == 0 ? true : false;
    }

}
