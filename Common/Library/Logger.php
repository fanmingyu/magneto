<?php

namespace Group\Common\Library;

use Group\Common\Library\TraceSdk;

class Logger
{

    private static $instance = null;

    /**
     * 单例化
     */
    private static function instance()
    {
        if (self::$instance === null)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private $logger = array();

    private $logId = '';

    private function __construct()
    {
        $configObj = getDI()->getConfig();
        if (isset($configObj['logger']) && $configObj['logger']) {
            $this->logId = sprintf('%x', (intval(microtime(true) * 10000) % 864000000) * 10000 + mt_rand(0, 9999));
            foreach ($configObj['logger'] as $key => $value) {
                $className = '\Group\Common\Library\Logger'.ucfirst($key).'Lib';
                $this->logger[] = new $className($value);
            }
        }
    }

    private function write($content, $level)
    {
        $type = TraceSdk::LOG_TYPE_TRACE;
        if ($level == 'ERROR') {
            $type = TraceSdk::LOG_TYPE_ERROR;
        } else if ($level == "INFO") {
            $type = TraceSdk::LOG_TYPE_INFO;
        } else if ($level == 'DEBUG') {
            $type = TraceSdk::LOG_TYPE_DEBUG;
        } else if ($level == "WARN") {
            $type = TraceSdk::LOG_TYPE_NOTICE;
        }

        $backtrace = debug_backtrace();
        $file = isset($backtrace[1]['file']) ? basename($backtrace[1]['file']) : '';
        $line = isset($backtrace[1]['line']) ? $backtrace[1]['line'] : '';
        if (!is_string($content)) {
            // 脱敏处理
            $content = self::cleanSensitiveField($content);
            TraceSdk::record($type, $file, $line, 'logger', $content);
            $content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } else {
            TraceSdk::record($type, $file, $line, 'logger', $content);
        }

        $content = strtr($content, "\n", ' ');
        foreach ($this->logger as $logger)
        {
            $logger->write("[$this->logId] [$level] [{$file}:{$line}] {$content}", $level);
        }
    }

    public static function getLogId()
    {
        return self::instance()->logId;
    }

    public static function setLogId($logId)
    {
        if (!empty($logId))
        {
            self::instance()->logId = $logId;
        }
    }

    public static function log($content, $level)
    {
        $l = new LoggerLevel($level);
        self::instance()->write($content, $l->getValue());
    }

    public static function debug($content)
    {
        self::instance()->write($content, 'DEBUG');
    }

    public static function info($content)
    {
        self::instance()->write($content, 'INFO');
    }

    public static function warn($content)
    {
        self::instance()->write($content, 'WARN');
    }

    public static function error($content)
    {
        self::instance()->write($content, 'ERROR');
    }

    // clean sensitive post field
    public static function cleanSensitiveField($data, $depth = 1) {
        // 保证数组的深度不要超过5层
        if (!is_array($data) || $depth > 5) {
            return $data;
        }

        $needRemoveField = [
            'password' => null,
            'old_password' => null,
            'new_password' => null ,
            're_new_password' => null,
            'confirmPassword' => null,
            'pwd' => null,
            'user_pwd' => null,
        ];

        $sensitiveFields = ['mobile', 'phone', 'account', 'user_name', 'username', 'bankcard', 'idno'];
        foreach ($data as $key=>$item1) {
            if (is_array($item1)) {
                $data[$key] = self::cleanSensitiveField($item1, $depth + 1);
            } else {
                // 提前退出
                if (!in_array($key, $sensitiveFields) && !array_key_exists($key, $needRemoveField)) {
                    continue;
                }

                if (in_array($key, $sensitiveFields)) {
                    $data[$key] = self::formatSensitiveField($item1, 3);
                } else if (array_key_exists($key, $needRemoveField)) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    public static function formatSensitiveField($name, $lengh = 2) {
        $name = trim($name);
        if (!$name) {
            return '';
        }

        $len = mb_strlen($name, 'utf8');
        $limit = $lengh * 2;
        if ($len > $limit) {
            return self::msubstr($name, 0, $lengh, 'utf-8', false) . '***'
                . self::msubstr($name, -$lengh, $lengh, 'utf-8', false);
        }

        return self::msubstr($name, 0, $lengh-1, 'utf-8', false)."***";
    }

    public static function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true) {
        if (function_exists("mb_substr")) {
            return mb_substr($str, $start, $length, $charset);
        } elseif (function_exists('iconv_substr')) {
            return iconv_substr($str, $start, $length, $charset);
        }

        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
        if ($suffix) {
            return $slice."…";
        }

        return $slice;
    }
}
