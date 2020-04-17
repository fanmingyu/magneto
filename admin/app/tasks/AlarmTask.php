<?php

use Group\Common\Library\Logger;
use Group\Magneto\Plugins\MailSendCloud;
use Group\Magneto\Plugins\Weixin;
use Group\Common\Library\Sms\Sms;

class AlarmTask extends \Phalcon\CLI\Task
{

    /**
     * 单次邮件最多告警个数
     */
    const ALARM_MAX_COUNT = 100;

    /**
     * 告警队列KEY前缀
     */
    const ALARM_KEY_LIST = 'ALARM_QUEUE_';

    /**
     * 上一次告警时间KEY前缀
     */
    const ALARM_KEY_LASTTIME = 'ALARM_LASTTIME_';

    const WEIXIN_APPID_ALARM = 2;

    private $_allUserInfo = NULL;

    /**
     * 读队列并发送告警
     */
    public function runAction()
    {
        $start = microtime(true);

        $configAll = $this->_getConfig();
        foreach ($configAll as $alarmKey => $config) {
            //发送时间间隔限制
            if (time() < $this->_getLastTime($alarmKey) + $config['time_limit']) {
                continue;
            }

            //发送条数限制
            $count = $this->_count($alarmKey);
            if ($count < 1 || $count < $config['trigger_limit']) {
                continue;
            }

            //发送告警
            $data = $this->_getAlarmDetails($alarmKey, min($count, self::ALARM_MAX_COUNT));

            //邮件
            $this->_sendMail($config['mail'], $config['title'], $data, $count);

            $summary = $this->_getSummary($data);

            //微信
            $content = sprintf("%s (共%s条) 概览[%s]\n===================\n%s\n%s", $config['title'], $count,
                implode(', ', $summary), date('Y-m-d H:i:s', $data[0]['createtime']),
                $data[0]['content']
            );
            $this->_sendWeixin($config['mail'], $content);

            //短信
            if ($count >= $config['sms_trigger_limit']) {
                $content = sprintf('%s/共%s条. 概览 %s', $config['title'], $count, implode('/', $summary));
                $this->_sendSms($config['mail'], $content);
            }

            $this->_monitorAdd('ALARMSEND_'.$alarmKey);
            $this->_updateLastTime($alarmKey);
        }

        Logger::info('AlarmTaskRun. cost:'.round(microtime(true) - $start, 3));
    }

    /**
     * 获取告警
     */
    private function _getAlarmDetails($alarmKey, $count)
    {
        $data = array();
        for ($i = 0; $i < $count; $i++) {
            $ret = getDI()->getShared('redis')->lPop(self::ALARM_KEY_LIST.$alarmKey);
            $data[] = json_decode($ret, true);
        }

        //发送一次后清理告警
        getDI()->getShared('redis')->del(self::ALARM_KEY_LIST.$alarmKey);

        return $data;
    }

    /**
     * 告警队列中的告警个数
     */
    private function _count($alarmKey)
    {
        return getDI()->getShared('redis')->lLen(self::ALARM_KEY_LIST.$alarmKey);
    }

    /**
     * 最后发送告警时间
     */
    private function _getLastTime($alarmKey)
    {
        return getDI()->getShared('redis')->get(self::ALARM_KEY_LASTTIME.$alarmKey);
    }

    /**
     * 更新最后发送告警时间
     */
    private function _updateLastTime($alarmKey)
    {
        return getDI()->getShared('redis')->set(self::ALARM_KEY_LASTTIME.$alarmKey, time());
    }

    /**
     * 累计量上报
     */
    private function _monitorAdd($key, $count = 1)
    {
        try {
            $redis = getDI()->getShared('redis');
            if (!$redis) {
                throw new \Exception("getRedisInstance failed");
            }

            $now = intval(time() / 60) * 60;
            return $redis->HINCRBY('H_MONITOR_'.$key, $now, $count);
        } catch (\Exception $e) {
            Logger::info("MonitorAddFailed. key:{$key}. message:".$e->getMessage());
        }
    }

    /**
     * 获取告警配置
     */
    private function _getConfig()
    {
        $configAll = array();
        $ret = getDI()->get('db_magneto')->fetchAll('SELECT * FROM alarm_config', \Phalcon\Db::FETCH_ASSOC);
        foreach ($ret as $item)
        {
            $item['mail'] = preg_split('/\s+/s', $item['mail']);
            $configAll[$item['name']] = $item;
        }

        return $configAll;
    }

    public function fixMailsAction()
    {
        $result = getDI()->get('db_magneto')->fetchAll('SELECT * FROM alarm_config', \Phalcon\Db::FETCH_ASSOC);
        foreach ($result as $item) {
            $id = $item['id'];
            $data = array(
                'mail' => implode(' ', preg_split('/\s+/s', trim($item['mail']))),
            );
            $this->_log('mail:'.$item['mail'].', mail2:'.$data['mail']);
            $ret = getDI()->get('db_magneto')->update('alarm_config', array_keys($data), array_values($data), "id='{$id}'");
        }
    }

    /**
     * 发送邮件
     */
    private function _sendMail(array $owner, $title, $data, $count)
    {
        return true;
        $summary = array();
        foreach ($data as $item) {
            $summary[$item['title']][] = $item;
        }

        $subject = "{$title}({$count}条)";
        $body = "<h3>{$title}({$count}条)</h3>";
        $body .= '<b style="color:red;">告警概览:</b>';
        $body .= '<ul style="font-size:px;color:#1f497d;font-weight:bold;">';
        foreach ($summary as $key => $item) {
            $body .= "<li><a href='#{$key}'>{$key}</a>: ".count($item)." 条</li>";
        }
        $body .= '</ul>';

        $body .= '<b style="color:red;">告警详情如下(最多展示100条):</b>';
        $body .= '<ol>';
        foreach ($data as $item) {
            $body .= "<a name='{$item['title']}'></a>";
            $body .= '<li style="font-size:12px;">';
            $body .= "<div style='color:#1f497d;margin-top:20px;'><b>告警标题: {$item['title']}</b></div>";
            $body .= "<div><b>告警时间: </b>".date('Y-m-d H:i:s', $item['createtime'])." (logId:<b>{$item['logId']}</b>)</div>";
            $body .= "<div><b>告警内容: </b>{$item['content']}</div>";
            $body .= "<div><b>文件位置: </b>{$item['file']}</div>";
            $body .= "<div><b>服务器IP: </b>{$item['serverIp']}</div>";
            $body .= '</li>';
        }
        $body .= '</ol>';

        $emailService = new \Group\Magneto\Admin\Service\EmailService();
        return $emailService->sendSync($owner, $subject, $body);
    }

    /**
     * 发送短信
     */
    private function _sendSms($owner, $content)
    {
        return true;
        $userInfo = $this->_getAllUserInfo();
        $contentEncode = urlencode($content);
        $mobiles = [];
        foreach ($owner as $item) {
            $mobile = isset($userInfo[$item]['mobile']) ? $userInfo[$item]['mobile'] : '';
            if (empty($mobile)) {
                $this->_log("SendSms. UserMobileIsNotSet. user:{$item}");
                continue;
            }
            $mobiles[] = $mobile;
        }

        foreach($mobiles as $m) {
            Sms::send('magneto', 'corp', $m, 'magneto', [$content]);
        }
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

        $result = Weixin::instance('alarm')->sendText($to, $content);
        $this->_log("SendWeixin. to:".implode(',', $to).", content:{$content}, result:" . json_encode($result, JSON_UNESCAPED_UNICODE));

        // todo 过渡阶段两个账号同时发送
        $result = Weixin::instance('corp-alarm')->sendText($to, $content);
        $this->_log("SendWeixinQY. to:".implode(',', $to).", content:{$content}, result:" . json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取概览信息
     */
    private function _getSummary($data)
    {
        $summary = array();
        foreach ($data as $item) {
            $summary[$item['title']][] = $item;
        }

        $summaryArray = array();
        foreach ($summary as $key => $item) {
            $summaryArray[] = "{$key}:".count($item)."条";
        }

        return $summaryArray;
    }

    /**
     * 获取用户手机号
     */
    private function _getAllUserInfo()
    {
        if ($this->_allUserInfo === NULL) {
            $this->_allUserInfo = array();
            $result = getDI()->get('db_magneto')->fetchAll("SELECT email, mobile FROM user WHERE mobile!=''", \Phalcon\Db::FETCH_ASSOC);
            foreach ($result as $item) {
                $this->_allUserInfo[$item['email']] = $item;
            }
        }

        return $this->_allUserInfo;
    }

    /**
     * 打LOG
     */
    private function _log($content)
    {
        $content  = '[Alarm] ' . $content;
        Logger::info($content);
    }
}
