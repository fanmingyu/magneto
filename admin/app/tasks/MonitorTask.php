<?php

use Group\Magneto\Admin\Daos\MonitorDAO;
use Group\Magneto\Admin\Daos\MonitorAlarmDAO;
use Group\Common\Library\Logger;
use Group\Magneto\Plugins\Weixin;
use Group\Common\Library\Sms\Sms;

class MonitorTask extends \Phalcon\CLI\Task
{

    private $now = 0;

    const KEY_MONITOR = 'H_MONITOR_';

    const KEY_MONITOR_ALLKEYS = 'STR_MONITOR_ALLKEYS';

    /**
     * 扫描所有的监控Key
     */
    public function scanKeysAction()
    {
        $_starttime = microtime(true);
        $result = MonitorDAO::scanAllKeys();
        $this->log('count:'.count($result));
        $this->log('end. cost:'.round(microtime(true) - $_starttime, 3));
    }

    /**
     * 数据落地到db
     */
    public function toDbAction()
    {
        $_starttime = microtime(true);

        $configAll = getDI()->get('db_magneto')->fetchAll('SELECT * FROM monitor_point_config', \Phalcon\Db::FETCH_ASSOC);
        $this->log('ToDbStart. configCount:'.count($configAll));

        foreach ($configAll as $config) {
            $this->saveDb($config['id'], $config['name']);
        }

        $this->log('ToDbEnd. cost:'.round(microtime(true) - $_starttime, 3));
    }

    /**
     * 告警判定
     */
    public function alarmAction()
    {
        $_starttime = microtime(true);

        $result = getDI()->get('db_magneto')->fetchAll('SELECT * FROM monitor_alarm_config ORDER BY `interval` ASC', \Phalcon\Db::FETCH_ASSOC);
        $this->log('AlarmStart. configCount:'.count($result));

        $configAll = array();
        foreach ($result as $item) {
            $configAll[$item['pointId']][] = $item;
        }

        //针对每个监控点
        foreach ($configAll as $pointId => $alarmConfig) {
            $point = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_point_config WHERE id='{$pointId}'", \Phalcon\Db::FETCH_ASSOC);
            if (empty($point)) {
                continue;
            }

            //取最大时间间隔
            $maxInterval = 0;
            $now = intval(time() / 60) * 60;
            foreach ($alarmConfig as $key => $item) {
                $alarmConfig[$key]['dataInterval'] = $this->getInterval($item['type'], $item['interval'], $now);
                $maxInterval = max($maxInterval, $alarmConfig[$key]['dataInterval']);
            }

            //取统计数据
            $data = MonitorDAO::getPointData($pointId, $point['name'], $now - $maxInterval * 60, $now);
            krsort($data);
            $this->log("AlarmGetStat. name:{$point['name']}, maxInterval:{$maxInterval}, dataCount:".count($data));

            //不统计最近一分钟
            if (isset($data[$now])) {
                unset($data[$now]);
            }

            //告警判断
            foreach ($alarmConfig as $config) {
                $this->alarmCheck($data, $now, $point, $config);
            }
        }

        $this->log('AlarmEnd. cost:'.round(microtime(true) - $_starttime, 3));
    }

    /**
     * 获取告警判断时间间隔
     */
    private function getInterval($type, $interval, $now)
    {
        if ($type === 'wave') {
            return $interval * 2;
        }

        //低峰时段最小值判断区间增大
        $hour = intval(date('Hi', $now));
        if ($type === 'min' && !($hour >= 800 && $hour <= 2200)) {
            return $interval * 10;
        }

        return $interval;
    }

    /**
     * 告警判断
     */
    private function alarmCheck($data, $now, $point, $config)
    {
        $sum = $this->getDataSum($data, $now - $config['dataInterval'] * 60);

        $content = '';

        //波动值
        if ($config['type'] === 'wave') {
            $current = $this->getDataSum($data, $now - $config['interval'] * 60);
            $previous = $sum - $current;

            $wave = $previous > 0 ? ($current - $previous) * 100 / $previous : 0;
            if (abs($wave) > $config['value']) {
                $content = "{$config['interval']}分钟数值{$previous}=>{$current}次，波动".round($wave, 1)."%，超过阈值{$config['value']}%。";
            }
        }

        //最大值
        if ($config['type'] === 'max') {
            if ($sum > $config['value']) {
                $content = "{$config['dataInterval']}分钟{$sum}次，超过阈值{$config['value']}。";
            }
        }

        //最小值
        if ($config['type'] === 'min') {
            if ($sum < $config['value']) {
                $content = "{$config['dataInterval']}分钟{$sum}次，低于阈值{$config['value']}。";
            }
        }

        if ($content !== '') {
            $content = "监控点[{$point['title']}]{$content}";

            $this->sendWeixin($point['owner'], $content.' '.date('Y-m-d H:i:s'));
            $this->sendSms($point['owner'], $content);
            $this->sendMail($point['owner'], "监控点[{$point['title']}]阈值告警", $content."<p><a href='/monitor/detail?name={$point['name']}'>点击查看</a></p>");

            MonitorAlarmDAO::createHistory($point['id'], $config['type'], $point['owner'], $content, $now);
        }
    }

    private function getDataSum($data, $endtime)
    {
        $sum = 0;
        foreach ($data as $key => $value) {
            if ($key < $endtime) {
                break;
            }
            $sum += $value;
        }

        return $sum;
    }

    private $allUserInfo = null;

    private function getAllUserInfo()
    {
        if ($this->allUserInfo === null) {
            $this->allUserInfo = array();
            $result = getDI()->get('db_magneto')->fetchAll("SELECT email, mobile FROM user WHERE mobile!=''", \Phalcon\Db::FETCH_ASSOC);
            foreach ($result as $item) {
                $this->allUserInfo[$item['email']] = $item;
            }
        }

        return $this->allUserInfo;
    }

    private function sendSms($owner, $content)
    {
        $userInfo = $this->getAllUserInfo();

        $contentEncode = urlencode($content);

        $ownerArray = explode(' ', $owner);
        $mobiles = [];
        foreach ($ownerArray as $item) {
            $mobile = isset($userInfo[$item]['mobile']) ? $userInfo[$item]['mobile'] : '';
            if (empty($mobile)) {
                $this->log("SendSms. UserMobileIsNotSet. user:{$item}");
                continue;
            }
            $mobiles[] = $mobile;
        }

        foreach ($mobiles as $m) {
            Sms::send('magneto', 'corp', $m, 'magneto', [$content]);
        }
    }

    private function sendMail($owner, $subject, $content)
    {
        $ownerArray = explode(' ', $owner);
        $emailService = new \Group\Magneto\Admin\Service\EmailService();
        $ret = $emailService->sendSync($ownerArray, $subject, $content);
    }

    private function sendWeixin($owner, $content)
    {
        $to = array();

        $ownerArray = explode(' ', $owner);
        foreach ($ownerArray as $item) {
            $to[] = substr($item, 0, strpos($item, '@'));
        }

        $result = Weixin::instance('monitor')->sendText($to, $content);
        $this->log("SendWeixin. to:".implode(',', $to).", content:{$content}, result:{$result}");

        $result = Weixin::instance('corp-monitor')->sendText($to, $content);
        $this->log("SendWeixin. to:".implode(',', $to).", content:{$content}, result:{$result}");
    }

    //此时间内数据不落库，以免这段时间还有写入
    const SAVEDB_DELAY_TIME = 180;

    private function saveDb($id, $name)
    {
        $this->now = intval(time() / 60) * 60;
        $statData = getDI()->get('redis')->HGETALL('H_MONITOR_'.$name);
        ksort($statData);

        $this->log("saveDbStart. id:{$id}, name:{$name}, redisCount:".count($statData));

        //导入db
        foreach ($statData as $time => $value) {
            //只处理3分钟以前
            if ($this->now - $time <= self::SAVEDB_DELAY_TIME) {
                continue;
            }

            $data = array(
                'pointId' => $id,
                'time' => $time,
                'value' => $value,
            );

            try {
                $ret = getDI()->get('db_magneto')->insert('monitor_point_value_'.($id % 16), $data, array_keys($data));
            } catch(\Exception $e) {
                $this->log('saveDbExcption. message:'.$e->getMessage());
                //幂等处理
                if (strpos($e->getMessage(), 'Duplicate entry') > 0) {
                    $ret = true;
                }
            }

            if ($ret) {
                getDI()->get('redis')->HDEL('H_MONITOR_'.$name, $time);
            }
        }

        //保存最后数值
        $lastTime = $this->now - 60;
        $lastValue = isset($statData[$lastTime]) ? $statData[$lastTime] : 0;
        getDI()->get('db_magneto')->update('monitor_point_config', array('lastvalue'), array($lastValue), "id='{$id}'");
    }

    private function log($content)
    {
        //echo date('[Y-m-d H:i:s] ').$content."\n";
        Logger::info($content);
    }

}
