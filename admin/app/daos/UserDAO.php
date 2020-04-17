<?php

namespace Group\Magneto\Admin\Daos;

use Group\Common\Library\Logger;

/**
 * 用户DAO
 */
class UserDAO
{

    /**
     * 获取所有的用户邮箱
     */
    public static function getAllEmails()
    {
        $result = getDI()->get('db_magneto')->fetchAll("SELECT email FROM user", \Phalcon\Db::FETCH_ASSOC);

        $emails = array();
        foreach ($result as $item) {
            $emails[] = $item['email'];
        }

        return $emails;
    }

}
