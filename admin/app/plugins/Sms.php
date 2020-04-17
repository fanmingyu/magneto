<?php

namespace Group\Magneto\Plugins;

use Group\Common\Library\Logger;
use Group\Common\Library\Sms\Sms as CommonSms;

/**
 * 短信模块(仅限告警、监控等内部通知)
 */
class Sms
{

    private static $instance = null;

    /**
     * 单例化
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() { }

    /**
     * 发送到账号
     */
    public function sendToAccount($account, $content)
    {
        $userInfo = array();
        $result = getDI()->get('db_magneto')->fetchAll("SELECT email, mobile FROM user WHERE mobile!=''", \Phalcon\Db::FETCH_ASSOC);
        foreach ($result as $item) {
            $userInfo[str_replace('@juren.com', '', $item['email'])] = $item;
        }


        $mobiles = [];
        foreach ($account as $item) {
            $mobile = isset($userInfo[$item]['mobile']) ? $userInfo[$item]['mobile'] : '';

            if (empty($mobile)) {
                Logger::info("SendSms. UserMobileIsNotSet. user:{$item}");
                continue;
            }
            $mobiles[] = $mobile;
        }

        foreach($mobiles as $m) {
            CommonSms::send("magneto", "corpname", $m, 'magneto', [$content]);
        }
    }

}
