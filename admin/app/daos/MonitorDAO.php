<?php

namespace Group\Magneto\Admin\Daos;

use Group\Common\Library\Logger;

/**
 * 业务监控系统DAO
 */
class MonitorDAO
{

    const KEY_MONITOR = 'H_MONITOR_';

    const KEY_MONITOR_ALLKEYS = 'STR_MONITOR_ALLKEYS';

    const KEY_MONITOR_ALLKEYS_SCANTIME = 'STR_MONITOR_ALLKEYS_SCANTIME';

    /**
     * 获取所有的配置
     */
    public static function getAllConfig()
    {
        $result = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_point_config ORDER BY id DESC", \Phalcon\Db::FETCH_ASSOC);
        foreach ($result as $item) {
            $data[$item['id']] = $item;
        }

        return $data;
    }

    /**
     * 获取所有的配置
     */
    public static function getConfigPage($key = '', $page, $pageSize, &$count, $sort)
    {
        $where = '';
        if ($key !== '') {
            $key = addslashes($key);
            $where = "WHERE name LIKE '%{$key}%' OR title LIKE '%{$key}%' OR owner LIKE '%{$key}%'";
        }

        $result = getDI()->get('db_magneto')->fetchOne("SELECT count(*) as total FROM monitor_point_config {$where}");
        $count = intval($result['total']);

        $where .= 'ORDER BY '.$sort.' LIMIT '.(($page - 1) * $pageSize).', '.intval($pageSize);

        return getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_point_config {$where}", \Phalcon\Db::FETCH_ASSOC);
    }

    /**
     * 获取点所有监控所属的视图
     */
    public static function getAllPointViews()
    {
        $result = getDI()->get('db_magneto')->fetchAll('SELECT points, viewid FROM monitor_view_point', \Phalcon\Db::FETCH_ASSOC);

        $pointViews = array();
        foreach ($result as $item) {
            $points = preg_split('/,/', $item['points']);
            foreach ($points as $point) {
                $pointViews[$point][] = $item['viewid'];
            }
        }

        return $pointViews;
    }

    /**
     * 获取所有视图监控点数统计
     */
    public static function getAllViewPointsCount()
    {
        $countMap = array();

        $result = getDI()->get('db_magneto')->fetchAll('SELECT viewid, count(*) as total FROM monitor_view_point GROUP BY viewid');
        foreach ($result as $item) {
            $countMap[$item['viewid']] = $item['total'];
        }

        return $countMap;
    }

    /**
     * 获取所有视图配置
     */
    public static function getAllViewConfig()
    {
        $result = getDI()->get('db_magneto')->fetchAll('SELECT * FROM monitor_view_config ORDER BY id DESC', \Phalcon\Db::FETCH_ASSOC);

        $viewConfig = array();
        foreach ($result as $item) {
            $viewConfig[$item['id']] = $item;
        }

        return $viewConfig;
    }

    /**
     * 根据监控点获取视图
     */
    public static function getViewsByPointName($name)
    {
        $result = getDI()->get('db_magneto')->fetchAll("SELECT viewid FROM monitor_view_point WHERE points='$name'", \Phalcon\Db::FETCH_ASSOC);
        if (empty($result)) {
            return array();
        }

        $viewIds = array();
        foreach ($result as $item) {
            $viewIds[] = $item['viewid'];
        }

        $views = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_view_config WHERE id IN (".implode(',', $viewIds).")", \Phalcon\Db::FETCH_ASSOC);
        return $views;
    }

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
     * 获取所有的Keys (读缓存)
     */
    public static function getAllKeys()
    {
        $result = getDI()->get('redis')->get(self::KEY_MONITOR_ALLKEYS);
        return array_flip(explode(',', $result));
    }

    /**
     * 获取Keys最后扫描时间
     */
    public static function getAllKeysScanTime()
    {
        return getDI()->get('redis')->get(self::KEY_MONITOR_ALLKEYS_SCANTIME);
    }

    /**
     * 扫描所有的Key
     */
    public static function scanAllKeys()
    {
        $result = getDI()->get('redis')->keys(self::KEY_MONITOR.'*');

        $allKeys = [];
        foreach ($result as $key) {
            $allKeys[] = substr($key, strlen(self::KEY_MONITOR));
        }

        $keys = array_flip($allKeys);
        $configAll = self::getAllConfig();
        foreach ($configAll as $item) {
            if (isset($keys[$item['name']])) {
                unset($keys[$item['name']]);
            }
        }

        // 删掉没有配置且没有使用的key
        foreach ($keys as $key => $value) {
            getDI()->get('redis')->del(self::KEY_MONITOR.$key);
        }

        getDI()->get('redis')->set(self::KEY_MONITOR_ALLKEYS, implode(',', $allKeys));
        getDI()->get('redis')->set(self::KEY_MONITOR_ALLKEYS_SCANTIME, time());

        return $allKeys;
    }

    private static $pointConfig = array();

    /**
     * 获取一个监控点的配置
     */
    public static function getPointConfig($name)
    {
        if (!isset(self::$pointConfig[$name])) {
            self::$pointConfig[$name] = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_point_config WHERE name='{$name}'", \Phalcon\Db::FETCH_ASSOC);
        }

        return self::$pointConfig[$name];
    }

    /**
     * 获取监控数据
     */
    public static function getPointData($pointId, $name, $start, $end)
    {
        $data = array();

        //获取Db数据
        if (!empty($pointId)) {
            $result = getDI()->get('db_magneto')->fetchAll("SELECT time, value FROM monitor_point_value_".($pointId % 16)." WHERE pointId='{$pointId}' AND time>='{$start}' AND time<='{$end}'", \Phalcon\Db::FETCH_ASSOC);
            foreach ($result as $item) {
                $data[intval($item['time'])] = $item['value'];
            }
        }

        //获取Redis数据
        $result = getDI()->get('redis')->hgetall(self::KEY_MONITOR.$name);
        $data += $result;

        return $data;
    }

    /**
     * 获取监控统计信息
     */
    public static function getPointStatInfo($pointId)
    {
        $min = getDI()->get('db_magneto')->fetchOne("SELECT min(time) as min FROM monitor_point_value_".($pointId % 16)." WHERE pointId='{$pointId}'");
        $max = getDI()->get('db_magneto')->fetchOne("SELECT max(time) as max FROM monitor_point_value_".($pointId % 16)." WHERE pointId='{$pointId}'");

        return array(
            'starttime' => $min['min'] ? date('Y-m-d H:i', $min['min']) : '',
            'endtime' => $max['max'] ? date('Y-m-d H:i', $max['max']) : '',
        );
    }

    /**
     * 计算监控波动数据
     */
    public static function calcPointWaveData($data)
    {
        $previous = 0;
        foreach ($data as $key => $value) {
            $data[$key] = $previous == 0 ? ($value == 0 ? 0 : 1) : round(($value - $previous) / $previous, 3);
            $previous = $value;
        }

        return $data;
    }

    public static function getStatRecently($pointId, $name)
    {
        $result = getDI()->get('redis')->hgetall(self::KEY_MONITOR.$name);

        $data = array();

        $end = time() - 60 * 10;
        foreach ($result as $key => $value) {
            if ($key > $end) {
                $data[$key] = intval($value);
            }
        }

        return $data;
    }

    /**
     * 解析指标名字
     */
    public static function parseNames($nameString)
    {
        $nameArray = array();

        $names = explode(',', $nameString);
        foreach ($names as $name) {
            $name = addslashes(trim($name));
            if (substr($name, -1) === '%') {
                $name = str_replace('_', '\_', $name);
                $key = substr($name, 0, strpos($name, '%'));
                $result = getDI()->get('db_magneto')->fetchAll("SELECT name FROM monitor_point_config WHERE name LIKE '{$key}%'", \Phalcon\Db::FETCH_ASSOC);
                $nameResult = array();
                foreach ($result as $item) {
                    $nameResult[] = $item['name'];
                }

                if (substr($name, -2) === '%%') {
                    $nameArray[] = implode('+', $nameResult);
                } else {
                    $nameArray += $nameResult;
                }
            } else {
                $nameArray[] = $name;
            }
        }

        return array_unique($nameArray);
    }

    public static function getTitleByName($name)
    {
        $name = addslashes($name);
        $result = getDI()->get('db_magneto')->fetchOne("SELECT title FROM monitor_point_config WHERE name='{$name}'", \Phalcon\Db::FETCH_ASSOC);

        return isset($result['title']) ? $result['title'] : '';
    }

    public static function getAllPointNames()
    {
        $names = array();

        $result = getDI()->get('db_magneto')->fetchAll("SELECT name FROM monitor_point_config ORDER BY name", \Phalcon\Db::FETCH_ASSOC);
        foreach ($result as $item) {
            $names[] = $item['name'];
        }

        return $names;
    }

    public static function getPointSum($id, $start, $end)
    {
        $result = getDI()->get('db_magneto')->fetchOne("SELECT sum(value) total FROM monitor_point_value_".($id % 16)." WHERE pointId='{$id}' AND time>='{$start}' AND time<='{$end}'", \Phalcon\Db::FETCH_ASSOC);
        return isset($result['total']) ? $result['total'] : 0;
    }

    public static function getIdleTime($key) {
        $result = getDI()->get('redis')->object('idletime', self::KEY_MONITOR . $key);
        return $result;
    }

    public static function add($key, $count = 1) {
        $time = intval(time() / 60) * 60;
        $result = getDI()->get('redis')->HINCRBY('H_MONITOR_'.$key, $time, $count);
        return $result;
    }
}
