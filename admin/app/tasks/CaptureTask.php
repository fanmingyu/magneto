<?php

use Group\Common\Library\Logger;
use Group\Magneto\Admin\Service\CaptureService;
use Group\Magneto\Plugins\Weixin;

class CaptureTask extends \Phalcon\CLI\Task
{
    // 1min
    const INTERVAL = 60;

    // 被截图的 monitor view
    const MONITOR_CAPTURE_URL = 'http://magneto.corp.com/monitorview/capture';

    /**
     * 截图
     */
    public function captureAction()
    {
        $now = time();
        $points = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_view_config WHERE capture_switch = '1'", \Phalcon\Db::FETCH_ASSOC);
        foreach ($points as $k => $point) {
            $check = $this->captureCheck($point, $now);
            if (!$check) {
                continue;
            }

            $url = self::MONITOR_CAPTURE_URL . '?id=' . $point['id'];

            $cs = new CaptureService();
            $imageBody = $cs->post(array(
                'url' => $url,
                'view_port_width' => 1080,
                'view_port_height' => 300,
                'clip_height' => 0,
                'clip_width' => 0,
                'clip_top' => 0,
                'clip_left' => 0,
            ), 10);

            $this->sendWeiXin($point['owner'], base64_decode($imageBody['data']));
        }
    }

    /**
     * 根据时间判断是否截图
     */
    private function captureCheck($point, $now)
    {
        $timePoints = explode(",", $point['capture_schedule']);
        foreach ($timePoints as $k => $time) {
            if ($now >= strtotime($time) && $now < strtotime($time) + self::INTERVAL) {
                return true;
            }
        }

        return false;
    }

    /**
     * 发送微信
     */
    private function sendWeiXin($owner, $content)
    {
        $to = array();

        $ownerArray = explode(' ', $owner);
        foreach ($ownerArray as $item) {
            $to[] = substr($item, 0, strpos($item, '@'));
        }

        $result = Weixin::instance('corp-monitor')->sendImage($to, $content);
        $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE);
        Logger::info("CaptureSendWeixin. to:".implode(',', $to).", result:{$resultJson}");
    }
}
