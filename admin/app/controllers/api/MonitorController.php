<?php

namespace Group\Magneto\Admin\Controllers\Api;

use Group\Magneto\Admin\Daos\MonitorDAO;

class MonitorController extends ApiBaseController
{
    /**
     * 不进行签名验证
     */
    protected $signatureEnable = false;

    /**
     * 求某个指标的总数 (只统计数据库数据)
     */
    public function sumAction()
    {
        $name = isset($_REQUEST['name']) ? trim(addslashes($_REQUEST['name'])) : '';
        $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
        $end = isset($_REQUEST['end']) ? intval($_REQUEST['end']) : 0;

        if (empty($name) || empty($start) || empty($end)) {
            $this->error(-1, '参数错误');
        }

        $point = MonitorDAO::getPointConfig($name);
        if (empty($point['id'])) {
            $this->error(-1, '监控点不存在');
        }

        $result = MonitorDAO::getPointSum($point['id'], $start, $end);

        $this->success('', array('total' => $result));
    }

    /**
     * 累计量上报
     **/
    public function addAction()
    {
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        $count = isset($_REQUEST['count']) ? intval($_REQUEST['count']) : 0;

        if (empty($name) || $count == 0) {
            $this->error(-1, "参数错误");
        }

        $redis = getDI()->get('redis');

        $now = intval(time() / 60) * 60;
        $redis->HINCRBY('H_MONITOR_'.$name, $now, $count);
        $this->success('监控添加成功');
    }

}
