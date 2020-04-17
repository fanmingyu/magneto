<?php

namespace Group\Magneto\Admin\Controllers\Api;

class AlarmController extends ApiBaseController
{

    const KEY_LIST = 'ALARM_QUEUE_';

    const KEY_LASTTIME = 'ALARM_LASTTIME_';

    const KEY_STAT = 'ALARM_STAT_';

    /**
     * 不进行签名验证
     */
    protected $signatureEnable = false;

    /**
     * 增加一条告警
     */
    public function pushAction()
    {
        $type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
        $title = isset($_REQUEST['title']) ? trim($_REQUEST['title']) : '';
        $content = isset($_REQUEST['content']) ? trim($_REQUEST['content']) : '';
        $logId = isset($_REQUEST['logId']) ? trim($_REQUEST['logId']) : '';

        if (empty($type)) {
            $this->error(-1, '参数错误:type不能为空');
        }

        if (empty($title)) {
            $this->error(-1, '参数错误:title不能为空');
        }

        $data = array(
            'title' => $title,
            'content' => $content,
            'logId' => $logId,
            'createtime' => time(),
        );
        $this->alarm($type, $data);

    }

    public function alarm($type, $data) {
        $redis = getDI()->get('redis');
        $redis->lPush(self::KEY_LIST.$type, json_encode($data));
        $redis->EXPIRE(self::KEY_LIST.$type, 86400 * 7);

        //todo 监控点上报
        $now = intval(time() / 60) * 60;
        $redis->HINCRBY('H_MONITOR_ALARM_'.$type, $now, 1);

        $this->success('告警添加成功');
    }

}
