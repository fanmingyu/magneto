<?php

namespace Group\Common\Library;

use Group\Common\Library\Curl;
use Group\Common\Library\CommonLogger;

class Alarm
{

    /**
     * 发送告警
     */
    public static function push($type, $title, $content = '')
    {
        $config = getDi()->getConfig()->alarm->toArray();
        $backtrace = debug_backtrace();

        $curl = Curl::instance();
        $result = $curl->post($config['host'], array(
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'hostname' => gethostname(),
            'file' => basename($backtrace[0]['file']).':'.$backtrace[0]['line'],
        ));

        CommonLogger::info("common alarm push. cost:{$curl->resultInfo['cost']}, result:{$result}, type:{$type}, title:{$title}, content:{$content}");
    }

}
