<?php

namespace Group\Magneto\Admin\Daos;

use Group\Common\Library\Logger;

/**
 * 业务监控系统DAO
 */
class MonitorAlarmDAO
{

    /**
     * 获取所有的告警配置数
     */
    public static function getAllAlarmCount()
    {
        $result = getDI()->get('db_magneto')->fetchAll('SELECT pointId, count(*) as total FROM monitor_alarm_config GROUP BY pointId', \Phalcon\Db::FETCH_ASSOC);

        $alarmCount = array();
        foreach ($result as $item) {
            $alarmCount[$item['pointId']] = $item['total'];
        }

        return $alarmCount;
    }

    /**
     * 创建历史记录
     */
    public static function createHistory($pointId, $type, $receiver, $content, $time)
    {
        $data = array(
            'pointId' => $pointId,
            'type' => $type,
            'receiver' => $receiver,
            'content' => $content,
            'createtime' => $time,
        );

        return getDI()->get('db_magneto')->insert('monitor_alarm_content', $data, array_keys($data));
    }

}
