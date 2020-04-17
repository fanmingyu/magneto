<?php

namespace Group\Magneto\Admin\Controllers\Api;

use Group\Magneto\Plugins\Weixin;

class CronAlarmController extends AlarmController
{
    /**
     * 增加一条告警
     */
    public function pushAction()
    {
        $data = file_get_contents("php://input");
        if (empty($data)) {
            $this->error(-1, "数据为空");
        }

        $data = json_decode($data, true);
        $alarmData = array(
            'title' => $data['Subject'],
            'content' => $data['Body'],
            'createtime' => time(),
        );

        $type = "CRONSUN_ALARM";
        if (!empty($data['To'])) {
            $this->_sendWeixin($data['To'], $this->_buildWeixinContent($data));
        }
        $this->alarm($type, $alarmData);
    }

    private function _buildWeixinContent($data)
    {
        return "Cronsun任务执行失败". "\n==================\n" . $data['Body'];
    }

    /**
     * 发送微信
     */
    private function _sendWeixin($owner, $content)
    {
        $to = array();
        foreach ($owner as $item) {
            $to[] = substr($item, 0, strpos($item, '@'));
        }

        Weixin::instance('alarm')->sendText($to, $content);
    }

}
