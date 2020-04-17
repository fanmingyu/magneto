<?php

namespace Group\Common\Library;

/**
 * 分布式系统日志跟踪埋点sdk
 */
class TraceSdk {
    /**
     * 分级日志 日志级别常量定义
     */
    // debug 信息，用于调试信息输出，默认不会输出，当在生产环境在线调试时使用
    const LOG_TYPE_DEBUG = 1;
    // trace
    const LOG_TYPE_TRACE = 2;
    // notice
    const LOG_TYPE_NOTICE = 3;
    // info 信息
    const LOG_TYPE_INFO = 4;
    // 错误 信息
    const LOG_TYPE_ERROR = 5;
    // 警报 信息
    const LOG_TYPE_EMEGENCY = 6;
    // 异常 信息
    const LOG_TYPE_EXCEPTION = 7;
    /**
     * 日志特殊类型
     */
    // 日志类型：xhprof性能日志
    const LOG_TYPE_XHPROF = 8;
    // 日志类型：耗时性能日志
    const LOG_TYPE_PERFORMENCE = 9;

    private static $logTypes = array(
        self::LOG_TYPE_DEBUG => 'DEBUG',
        self::LOG_TYPE_TRACE => 'TRACE',
        self::LOG_TYPE_NOTICE => 'NOTICE',
        self::LOG_TYPE_INFO => 'INFO',
        self::LOG_TYPE_ERROR => 'ERROR',
        self::LOG_TYPE_EMEGENCY => 'EMEGENCY',
        self::LOG_TYPE_EXCEPTION => 'EXCEPTION',
        self::LOG_TYPE_XHPROF => 'XHPROF',
        self::LOG_TYPE_PERFORMENCE => 'PERFORMENCE',
    );

    // is disable the work
    private static $_enable = true;
    // is init flag
    private static $_isinit = false;
    // current request trace id 此次请求的唯一UUID
    private static $_traceid = "";
    // current rpc id 此次请求的调用累加rpcid
    private static $_rpcid = "0";
    // rpcid + seq is current rpcid
    private static $_seq = 0;
    // cacheed log 日志存储变量
    private static $_log = array();
    // request start
    private static $_starttime;
    // current idc and ip
    private static $_idc;
    private static $_ip;
    // project name 当前项目名称，会作为日志保存目录名
    private static $_project = "";
    // log level 默认分级日志级别，生产环境一般都是Error
    private static $_log_level = self::LOG_TYPE_ERROR;
    // 如果url内带参数那么url会很难做汇总
    // 用回调方式兼容多个方式
    private static $_urlruleCallback = null;
    // meta other parameter 附加在meta日志内，不建议存太多，会影响性能
    private static $_uid = 0;
    private static $_env = "";
    private static $_extra = array();
    private static $_devmode = false;
    private static $ch;

    private static $string = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private static $encodeBlockSize = 7;
    private static $decodeBlockSize = 4;

    // current SDK Version
    const VERSION = "v0.4.0";

    /**
     * 注入traceId和rpcId
     * @param string $traceId 唯一跟踪id，一般是通过header传递，对于无法传递的情况，才采用参数传入
     * @param int $rpcId rpc请求id，一般是通过header传递，对于无法传递的情况，才采用参数传入
     */
    public static function injectHeaders($traceId = '', $rpcId = 0) {
        // 检测是否启用
        if (!self::isEnable()) {
            return;
        }

        // if set the rpcid header
        if (!empty($rpcId)) {
            self::$_rpcid = trim($rpcId);
        } else if (!empty($_SERVER["HTTP_X_RPCID"])) {
            self::$_rpcid = trim($_SERVER["HTTP_X_RPCID"]);
        }

        // if set the traceid header
        if (!empty($traceId)) {
            self::$_traceid = trim($traceId);
        } else if (!empty($_SERVER["HTTP_X_TRACEID"])) {
            self::$_traceid = trim($_SERVER["HTTP_X_TRACEID"]);
        } else {
            //trace id not exist general one
            self::getTraceID();
        }

        // traceid rpcid
        header("X-TRACEID: " . self::$_traceid);
        header("X-RPCID: " . self::$_rpcid);
    }

    /**
     * init 初始化系统状态
     * @param string $projectName 当前项目名称建议小写英文字母下划线
     * @throws |Exception
     */
    public static function init($projectName) {
        // make an tips of the System Bit limit
        if (PHP_INT_SIZE != 8) {
            echo "TraceSDK Can't work on 32Bit Operating System or PHP x86 version";
            exit;
        }

        // 检测是否启用
        if (!self::isEnable()) {
            return;
        }

        // is init already
        if (self::$_isinit) {
            return;
        }

        // init flag
        self::$_isinit = true;
        // Initial, we read two configurations from php.ini
        $env = get_cfg_var("phalcon.env");
        self::$_env = $env;

        // idc
        if (isset($_SERVER["TRACE_IDC"])
            && trim($_SERVER["TRACE_IDC"]) !== ""
            && $_SERVER["TRACE_IDC"] >= 0
            && $_SERVER["TRACE_IDC"] <= 3) {
            self::$_idc = trim($_SERVER["TRACE_IDC"]) . "";
        } else {
            self::$_idc = 0;
        }

        self::$_project = trim($projectName);
        if (strlen(self::$_project) == 0) {
            throw new \Exception("TraceSdk:请指定初始化的项目名称参数", 1001);
        }

        // get server ip
        if (isset($_SERVER["TRACE_IP"])
            && $_SERVER["TRACE_IP"] != ""
            && count(explode(".", $_SERVER["TRACE_IP"])) == 4) {
            self::$_ip = $_SERVER["TRACE_IP"];
        } else {
            if (in_array($env, ['product', 'preproduct'])) {
                self::$_ip = '11.11.11.11';
            } else {
                self::$_ip = self::getServerIp();
            }
        }

        // record start time
        self::$_starttime = microtime(true);

        // set log level
        if (!empty($_SERVER["HTTP_X_LOGLEVEL"])) {
            self::$_log_level = trim($_SERVER["HTTP_X_LOGLEVEL"]);
        }

        self::injectHeaders();

        // shutdown process
        register_shutdown_function(function () {
            self::shutdown();
        });
    }

    /**
     * 此功能不是给任何开发使用的
     * 只是用来此SDK开发测试使用
     * 任何业务代码禁止调用
     */
    public static function devmode() {
        self::$_devmode = true;
        self::$_idc = "0";
        self::$_project = "trace_dev";
        self::$_ip = "11.11.11.11";
        self::$_isinit = true;
        self::$_starttime = microtime(true);

        // shutdown process
        register_shutdown_function(function () {
            self::shutdown();
        });
    }

    /**
     * 用于检测当前是否启用状态
     * 由于启用状态检测涉及多个选项统一用一个函数处理了
     */
    public static function isEnable() {
        // 仅供本SDK开发人员自行调试使用
        if (self::$_devmode) {
            return true;
        }

        // 被禁用不工作
        if (!self::$_enable) {
            return false;
        }

        // 命令行启用的脚本，不工作
        if (php_sapi_name() == "cli") {
            return false;
        }

        return true;
    }

    /**
     * 如果url内包含请求参数，会导致url做聚合和统计很难唯一
     * 通过这个函数将一些url进行唯一化
     * 如xxx.weibo.com/v1/log/12312312/lists.json xxx.weibo.com/v1/log/4567/lists.json
     * 过滤成xxx.weibo.com/v1/log/filteredparam/lists.json
     * 使用之前请setUrlFilterCallback指定规则
     * @param string $url
     * @param bool $hasquery result with query parameter
     * @return string $url
     */
    public static function urlFilter($url, $hasquery = false) {
        if (self::$_urlruleCallback != null) {
            return call_user_func(self::$_urlruleCallback, $url, $hasquery);
        } else {
            return $url;
        }
    }

    /**
     * url过滤回调注册，如果不指定请指定为null
     * 每个网站的url规则多变，单纯的模板是无法通用的，只好这么做了
     * @param callback $callback url处理回调函数可以为null
     */
    public static function setUrlFilterCallback($callback) {
        self::$_urlruleCallback = $callback;
    }

    /**
     * 设置附加metalog信息,不建议放太多东西
     * @param string $uid 用户uid
     * @param array $extra 附加记录信息数组 不建议放不规则的数据结构
     */
    public static function setMeta($uid, $env, $extra) {
        self::$_uid = $uid;
        self::$_env = $env;
        self::$_extra = $extra;
    }

    /**
     * 如使用此功能必须执行在init之前
     * 设置日志记录级别，低于这个级别的日志不会记录到日志
     * @param $level
     * @throws |Exception
     */
    public static function setLogLevel($level) {
        if (!is_numeric($level) || $level < 1 || $level > 7) {
            throw new \Exception("日志等级取值范围:1-7", 1008);
        }
        self::$_log_level = trim($level);
    }

    /**
     * 判断是否json串
     */
    public static function isJson($str) {
        if (!is_string($str)) {
            return false;
        }

        json_decode($str);
        return json_last_error() == JSON_ERROR_NONE;
    }

    /**
     * 格式化msg
     */
    private static function formatMsg($content) {
        return self::isJson($content) ? json_decode($content, true) : $content;
    }

    /**
     * 记录业务日志
     * @param string $type 日志类型本地LOG_TYPE常量,代表日志等级
     * @param string $file 文件路径
     * @param int $line 写此日志的文件行数
     * @param string $tag 用户自定义tag，用来区分日志类型的
     * @param string|array $content 日志内容
     */
    public static function record($type, $file, $line, $tag, $content) {
        // 检测是否可用
        if (!self::$_enable) {
            return;
        }

        if ($type < self::$_log_level) {
            // ignore the low level log
            return;
        }

        // record on var
        // t type ,p path,l line, m msg,g tag,e time,c cost
        self::$_log[] = array(
            "r" => self::getChildRPCID(),
            "t" => $type,
            "e" => microtime(true),
            "g" => $tag,
            "p" => $file,
            "l" => $line,
            "m" => self::formatMsg($content)
        );
    }

    /**
     * 手动性能埋点开始，此函数会返回一组数据，这个数据是给digLogEnd函数使用的
     * @param string $file 当前埋点文件路径
     * @param int $line 当前行
     * @param string $tag 性能标签比如:模块名_函数名_xxx
     * @return array
     */
    public static function digLogStart($file, $line, $tag) {
        // 检测是否初始化并且未禁用
        if (!self::isEnable()) {
            return;
        }

        return array(
            "file" => $file,
            "line" => $line,
            "tag" => $tag,
            "start" => microtime(true),
            "rpcid" => self::getChildNextRPCID(),
        );
    }

    /**
     * 手动性能埋点结束，传入之前埋点函数返回的数据到这里即可产生日志
     * @param array $config 配置信息
     * @param string|array $msg 附加文字信息
     */
    public static function digLogEnd($config, $msg) {
        // 检测是否初始化并且未禁用
        if (!self::isEnable()) {
            return;
        }

        // make sure the msg is array
        if (!is_array($msg)) {
            $msg = self::isJson($msg) ? json_decode($msg, true) : array($msg);
        }

        // replace the special url
        if (is_array($msg) && isset($msg["url"])) {
            $msg["url"] = self::urlFilter($msg["url"]);
        }

        // record on var
        // t type ,p path,l line, m msg,g tag,e time,c cost
        self::$_log[] = array(
            "t" => self::LOG_TYPE_PERFORMENCE,
            "e" => microtime(true),
            "g" => $config["tag"],
            "p" => $config["file"],
            "l" => $config["line"],
            "c" => bcsub(microtime(true), $config["start"], 4),
            "m" => $msg,
            "r" => $config["rpcid"],
        );
    }

    /**
     * mysql性能埋点结束，使用digLogStart作为开始
     * 此函数只是digLogEnd的封装
     * @param array $digPoint 埋点digLogStart返回的值
     * @param string $sql 此次执行的sql
     * @param array $data 此次执行的sql配套的data没有直接传入array()
     * @param string $op 此次操作类型如select update delete insert
     * @param string $fun 此次埋点相关函数名，仅供备注
     */
    public static function digMysqlEnd($digPoint, $sql, $data, $op, $fun) {
        self::digLogEnd($digPoint, array(
            "sql" => $sql,
            "data" => $data,
            "op" => $op,
            "fun" => $fun
        ));
    }

    /**
     * curl性能埋点结束时调用，使用digLogStart作为开始
     * @param array $digPoint 埋点digLogStart返回的值
     * @param string $url 请求的url
     * @param string $method 请求的动作post get delete put 等
     * @param array $postParam post的参数
     * @param array $getParam get的参数
     * @param array $curlInfo curl_getinfo(handle)返回的内容
     * @param string $errCode 错误时产生的code curl_errno(handle)函数
     * @param string $errMsg 错误时获取到的msg curl_error(handle)函数
     * @param string $result
     */
    public static function digCurlEnd($digPoint, $url, $method, $postParam, $getParam, $curlInfo, $errCode, $errMsg, $result) {
        self::digLogEnd($digPoint, array(
            "url" => self::urlFilter($url, true),
            "orgurl" => $url,
            "method" => $method,
            "param" => array("post" => self::formatMsg($postParam), "get" => $getParam),
            "info" => $curlInfo,
            "error" => array("errorno" => $errCode, "error" => $errMsg),
            "result" => self::formatMsg($result),
        ));
    }

    /**
     * 获取当前请求的traceid，如果没有设置自动生成一个
     * @return string|boolean
     */
    public static function getTraceID() {
        if (trim(self::$_traceid) == "") {
            // prepare parameter
            $idc = self::$_idc;//2bit
            $ip = self::$_ip;//16bit
            $ip = explode(".", $ip);
            $ip = $ip[2] * 256 + $ip[3];
            $time = microtime();//28bit + 10bit
            $time = explode(" ", $time);
            $ms = intval($time[0] * 1000);
            $time = $time[1] - strtotime("2017-1-1");
            $rand = mt_rand(0, 255);//4
            $key = TraceId::encode($idc, $ip, $time, $ms, $rand);
            $key = self::encode($key);
            self::$_traceid = $key;
        }

        return self::$_traceid;
    }

    public static function decodeTraceID($traceid) {
        $traceid = self::decode($traceid);
        $result = TraceId::decode($traceid);
        $result["time"] = strtotime("2017-01-01") + $result["time"];
        $ip1 = (int)($result["ip"] / 256);
        $ip2 = (int)($result["ip"] % 256);
        $result["ip"] = $ip1 . "." . $ip2;
        return $result;
    }

    /**
     * 获取当前请求的RPCid
     * @return string
     */
    public static function getCurrentRPCID() {
        return self::$_rpcid;
    }

    /**
     * 获取当前子请求的RPCID，发送请求用，请不要使用这个
     * @return string
     */
    public static function getChildRPCID() {
        return self::$_rpcid . "." . self::$_seq;
    }

    /**
     * 获取下一个子请求的RPCID，getChildRPCID也会跟随变化，发送请求的时候用这个
     * @return string
     */
    public static function getChildNextRPCID() {
        self::$_seq++;
        return self::$_rpcid . "." . self::$_seq;
    }

    /**
     * 获取子请求的header参数，已经包含了getChildNextRPCID
     * @return array
     */
    public static function getChildCallParam() {
        // 检测是否初始化并且未禁用
        if (!self::isEnable()) {
            return array();
        }

        $headers = array(
            "X-RPCID" => self::getChildNextRPCID(),
            "X-TRACEID" => self::getTraceID(),
            "X-LOGLEVEL" => self::$_log_level,
        );
        return $headers;
    }

    /**
     * 获取子请求的curl header参数，已经包含了getChildNextRPCID
     * 通过这个函数获取下一次Curl请求所需的Header值
     * 如果指定了digstart返回的数组会使用当前rpciid
     * @param $digpoint array digpoint埋点
     * @return array
     */
    public static function getCurlChildCallParam($digpoint = array()) {
        // 检测是否初始化并且未禁用
        if (!self::isEnable()) {
            return array();
        }

        $headers = array(
            "X-TRACEID: " . self::getTraceID(),
            "X-LOGLEVEL: " . self::$_log_level,
        );

        if (isset($digpoint["rpcid"])) {
            $headers[] = "X-RPCID: " . $digpoint["rpcid"];
        } else {
            $headers[] = "X-RPCID: " . self::getChildNextRPCID();
        }

        return $headers;
    }

    /**
     * 当所有处理完毕后会触发这个函数进行收尾
     * 注意这里会关闭用户请求连接异步做一些事情，这个函数必须在最后执行
     * shutdow注册有顺序，请关注
     */
    public static function shutdown() {
        // 检测是否初始化并且未禁用
        if (!self::isEnable()) {
            return;
        }

        //release the session handle
        // 注意这里会关闭链接异步做一些事情，所以这个函数必须在最后执行
        \session_write_close();
        // finished the request
        if (function_exists("fastcgi_finish_request")) {
            \fastcgi_finish_request();
        }

        // 获取最后一次产生的错误
        $error = error_get_last();
        var_dump($error);exit;
        if ($error) {
            self::record(self::LOG_TYPE_ERROR, $error["file"], $error["line"], __FUNCTION__,
                array(
                    "type" => $error['type'],
                    "msg" => $error['message'],
                    "url" => self::getCurrentUrl(),
                    "from" => self::getReferer(),
                    "clientip" => self::getClientIp(),
                    "localip" => self::$_ip,
                )
            );
        } else {
            // 记录此次请求的一些附加信息到日志
            self::record(self::LOG_TYPE_TRACE, __FILE__, __LINE__, __FUNCTION__,
                array(
                    "from" => self::getReferer(),
                    "clientip" => self::getClientIp(),
                    "localip" => self::$_ip,
                    "ua" => self::getUserAgent(),
                    "url" => self::getCurrentUrl(),
                )
            );
        }

        // dump meta log
        // self::dumpMetaLog();
        // dump common log
        self::dumpCommonLog();

        if (self::$ch) {
            curl_close(self::$ch);
        }
    }

    /**
     * 禁用当前功能，不会产生日志
     * 用于不希望产生日志的请求
     */
    public static function disable() {
        self::$_enable = false;
    }

    private static function dumpMetaLog() {
        $time = self::$_starttime;
        $log = array(
            "version" => self::VERSION,
            "rpcid" => self::getCurrentRPCID(),
            "traceid" => self::getTraceID(),
            "time" => self::$_starttime,
            "@timestamp" => date("Y-m-d", $time) . "T" . date("H:i:s", $time) . "Z",
            "elapsed_ms" => bcsub(microtime(true), self::$_starttime, 4),
            "perf_on" => 0,
            "ip" => self::$_ip,
            "rt_type" => php_sapi_name(),
            "uid" => self::$_uid . "",
            'env' => self::$_env,
        );

        if (isset($_SERVER["REQUEST_URI"]) && strlen($_SERVER["REQUEST_URI"]) > 0) {
            $url = $_SERVER["REQUEST_URI"];
            $log["url"] = self::urlFilter("http://" . $_SERVER['HTTP_HOST'] . $url);
//            $log['param'] = array("get" => $_GET, "post" => $_POST);
        } else {
            $log["url"] = $_SERVER["PHP_SELF"];
        }

        // record the httpcode
        if (function_exists("http_response_code") && http_response_code() > 0) {
            $log["httpcode"] = http_response_code();
        } else {
            $log["httpcode"] = -1;
        }

        $log["project"] = self::$_project;
        $log["extra"] = self::$_extra;
        $logstr = json_encode($log, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $logstr = '{"create":{"_index":"' . self::$_project . '-' . date("Ymd") . '","_type":"' . self::$_project . '"}}' . "\n" . $logstr . "\n";
        //base 64 for encode the \n
        $logstr = base64_encode($logstr);
        //save to syslog
        self::writeMsg(self::$_project, "meta", $logstr);
    }

    private static function dumpCommonLog() {
        // if there is no log
        if (count(self::$_log) == 0) {
            return;
        }

        // filter the large msg
        foreach (self::$_log as $k => $v) {
            $msg = is_array($v['m']) ? json_encode($v["m"], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $v['m'];
            if (strlen($msg) > 80960) {
                self::$_log[$k]["m"] = substr($msg, 0, 80960);
            }
        }

        // if the count too much
        if (count(self::$_log) > 30) {
            $list = array_chunk(self::$_log, 30);
            $result = array(
                "key" => self::getTraceID(),
                "rpcid" => self::getCurrentRPCID(),
                "val" => "",
                "timestamp" => time(),
            );

            foreach ($list as $logitem) {
                $result["val"] = $logitem;
                self::writeMsg(self::$_project, "log", $result);
            }
        } else {
            $result = array(
                "key" => self::getTraceID(),
                "rpcid" => self::getCurrentRPCID(),
                "val" => self::$_log,
                "timestamp" => time(),
            );

            self::writeMsg(self::$_project, "log", $result);
        }

        //clean up
        self::$_log = array();
    }

    private static function getMsgDir($topic) {
        if (!isset($_SERVER["TRACE_LOGPATH"])) {
            // 默认的trace目录
            $_rootPath = '/apps/logs/trace';
        } else {
            $_rootPath = $_SERVER["TRACE_LOGPATH"];
        }

        return $_rootPath . DIRECTORY_SEPARATOR . date("Ym") . DIRECTORY_SEPARATOR;
    }

    private static function checkdir($path) {
        // must absolute path
        if (!is_dir($path)) {
            return mkdir($path, 0777, true);
        }
        return true;
    }

    // 将日志推到远程server
    private static function pushLogToServer($url, $data) {
        if (!self::$ch) {
            $ch = curl_init();
            self::$ch = $ch;
        } else {
            $ch = self::$ch;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "contents=".$data);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);          // 注意，毫秒超时一定要设置这个
        // 经测试可用，不要阻塞业务
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1);        // 超时毫秒，cURL 7.16.2中被加入。从PHP 5.2.3起可使用
        // curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (substr($url, 0, 5) === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // 信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 检查证书中是否设置域名
        }

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        return $errno > 0 && $errno != 28 ? false : true;
    }

    /**
     * 格式化日志输出，主要为了输出到文件
     * 
     * @param $topic array 项目名
     * @param $data array 日志数据
     * @return string
     */
    private static function formatLogMsg($topic, array $data) {
        $logStr = '';
        if (empty($data['val'])) {
            return $logStr;
        }

        $prefix = '['.$data['key'].':'.$data['rpcid'].'] ['.$topic.'] ['.date('Y-m-d H:i:s', $data['timestamp']).'] ';
        foreach ($data['val'] as $msgVal) {
            if (!is_array($msgVal)) {
                $logStr .= $prefix.$msgVal.PHP_EOL;
                continue;
            }

            $logLevel = self::$logTypes[$msgVal['t']];
            $logStr .= $prefix.'['.$logLevel.'] ['.$msgVal['g'].'] ['.basename($msgVal['p']).':'.$msgVal['l'].'] '
                .(is_array($msgVal['m']) ? json_encode($msgVal['m'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $msgVal['m']).PHP_EOL;

        }

        return $logStr;
    }

    // 写入消息
    private static function writeMsg($topic, $type, $data) {
        // 这里先尝试推到远程日志，等后期日志服务器稳定后，才采用分布式日志系统
        // @todo 需要完善
        // 判断是否直接传给server，只有在开发和测试环境，才能开启直接传递日志到server
        $res = false;
        $config = getDI()->getConfig();
        if (isset($config['trace']['logServer']) && $config['trace']['logServer']) {    
            $logStr = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
            // 配置了logServer
            $host = $config['trace']['logServer'];
            if ($type == 'log') {
                $res = self::pushLogToServer("http://" . $host . "/ragnar/log/bizlog/put", $logStr);
            }

            if ($type == 'meta') {
                $res = self::pushLogToServer("http://" . $host . "/ragnar/log/metalog/put", $logStr);
            }
        }

        // 如果已经处理成功了，直接返回
        if ($res) return true;

        $logStr = is_array($data) ? self::formatLogMsg($topic, $data) : $data;

        // write log for logagent
        $logpath = self::getMsgDir($topic . "_" . $type);
        // check the dir and create it
        $ret = self::checkdir($logpath);
        // create dir fail
        if (!$ret) {
            return false;
        }

        // put data to log
        $fsret = file_put_contents($logpath . date("d") . ".log", trim($logStr) . PHP_EOL, FILE_APPEND);
        // if fail write
        if (!$fsret) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 将 mid 转化成 62 进制字符串
     *
     * @param mixed $mid
     * @access public
     * @return string
     */
    public static function encode($mid) {
        $str = '';
        $midlen = strlen($mid);
        $segments = ceil($midlen / self::$encodeBlockSize);
        $start = $midlen;
        for ($i = 1; $i < $segments; $i += 1) {
            $start -= self::$encodeBlockSize;
            $seg = substr($mid, $start, self::$encodeBlockSize);
            $seg = self::encodeSegment($seg);
            $str = str_pad($seg, self::$decodeBlockSize, '0', STR_PAD_LEFT) . $str;
        }
        $str = self::encodeSegment(substr($mid, 0, $start)) . $str;
        return $str;
    }

    /**
     * 将62进制字符串转成10进制mid
     *
     * @param mixed $str
     * @param mixed $compat
     * @param mixed $forMid
     * @access public
     * @return string
     */
    public static function decode($str, $compat = false, $forMid = true) {
        $mid = '';
        $strlen = strlen($str);
        $segments = ceil($strlen / self::$decodeBlockSize);
        $start = $strlen;
        for ($i = 1; $i < $segments; $i += 1) {
            $start -= self::$decodeBlockSize;
            $seg = substr($str, $start, self::$decodeBlockSize);
            $seg = self::decodeSegment($seg);
            $mid = str_pad($seg, self::$encodeBlockSize, '0', STR_PAD_LEFT) . $mid;
        }

        $mid = self::decodeSegment(substr($str, 0, $start)) . $mid;
        if ($compat && !in_array(substr($mid, 0, 3), array('109', '110', '201', '211', '221', '231', '241'))) {
            $mid = self::decodeSegment(substr($str, 0, 4)) . self::decodeSegment(substr($str, 4));
        }

        if ($forMid) {
            if (substr($mid, 0, 1) == '1' && substr($mid, 7, 1) == '0') {
                $mid = substr($mid, 0, 7) . substr($mid, 8);
            }
        }

        return $mid;
    }

    /**
     * 将10进制转换成62进制
     *
     * @param mixed $str
     * @static
     * @access private
     * @return string
     */
    private static function encodeSegment($str) {
        $out = '';
        while ($str > 0) {
            $idx = $str % 62;
            $out = substr(self::$string, $idx, 1) . $out;
            $str = floor($str / 62);
        }
        return $out;
    }

    /**
     * 将62进制转换成10进制
     *
     * @param mixed $str
     * @access private
     * @return string
     */
    private static function decodeSegment($str) {
        $out = 0;
        $base = 1;
        for ($t = strlen($str) - 1; $t >= 0; $t -= 1) {
            $out = $out + $base * strpos(self::$string, substr($str, $t, 1));
            $base *= 62;
        }

        return $out . "";
    }

    /**
     * 修正过的ip2long
     *
     * 可去除ip地址中的前导0。32位php兼容，若超出127.255.255.255，则会返回一个float
     * for example: 02.168.010.010 => 2.168.10.10
     *
     * 处理方法有很多种，目前先采用这种分段取绝对值取整的方法吧……
     * @param string $ip
     * @return float 使用unsigned int表示的ip。如果ip地址转换失败，则会返回0
     */
    public static function ip2long($ip) {
        $ip_chunks = explode('.', $ip, 4);
        foreach ($ip_chunks as $i => $v) {
            $ip_chunks[$i] = abs(intval($v));
        }

        return sprintf('%u', ip2long(implode('.', $ip_chunks)));
    }

    /**
     * 判断是否是内网ip
     *
     * @param string $ip
     *
     * @return boolean
     */
    private static function isPrivateIp($ip) {
        $ip_value = self::ip2long($ip);
        return ($ip_value & 0xFF000000) === 0x0A000000 || //10.0.0.0-10.255.255.255
            ($ip_value & 0xFFF00000) === 0xAC100000 || //172.16.0.0-172.31.255.255
            ($ip_value & 0xFFFF0000) === 0xC0A80000; //192.168.0.0-192.168.255.255
    }

    /**
     * 获取真实的客户端ip地址
     *
     * This function is copied from login.sina.com.cn/module/libmisc.php/get_ip()
     * @param boolean $to_long 可选。是否返回一个unsigned int表示的ip地址
     * @return mixed string or float 客户端ip。如果to_long为真，则返回一个unsigned int表示的ip地址；否则，返回字符串表示。
     */
    private static function getClientIp($to_long = false) {
        $forwarded = self::getServer('HTTP_X_FORWARDED_FOR');
        if ($forwarded) {
            $ip_chains = explode(',', $forwarded);
            $proxied_client_ip = $ip_chains ? trim(array_pop($ip_chains)) : '';
        }

        if (self::isPrivateIp(self::getServer('REMOTE_ADDR')) && isset($proxied_client_ip)) {
            $real_ip = $proxied_client_ip;
        } else {
            $real_ip = self::getServer('REMOTE_ADDR');
        }

        return $to_long ? self::ip2long($real_ip) : $real_ip;
    }

    /**
     * 获取当前Referer
     * @param boolean $urlencode 是否urlencode后返回，默认true
     * @return string
     */
    private static function getReferer($urlencode = false) {
        return $urlencode ? rawurlencode(self::getServer('HTTP_REFERER')) : self::getServer('HTTP_REFERER');
    }

    /**
     * 得到当前请求的环境变量
     *
     * @param string $name
     *
     * @return mixed string or null 当$name指定的环境变量不存在时，返回null
     */
    private static function getServer($name) {
        return isset($_SERVER[$name]) ? $_SERVER[$name] : null;
    }

    /**
     * 获取当前ua
     * Method  getUserAgent
     *
     * @return string
     */
    private static function getUserAgent() {
        return self::getServer('HTTP_USER_AGENT');
    }

    /**
     * 返回当前url
     *
     * @param boolean $urlencode 是否urlencode后返回，默认true
     * @return string
     */
    private static function getCurrentUrl($urlencode = false) {
        $req_uri = self::getServer('REQUEST_URI');
        if (null === $req_uri) {
            $req_uri = self::getServer('PHP_SELF');
        }

        $https = self::getServer('HTTPS');
        $s = null === $https ? '' : ('on' == $https ? 's' : '');
        $protocol = self::getServer('SERVER_PROTOCOL');
        $protocol = strtolower(substr($protocol, 0, strpos($protocol, '/'))) . $s;
        $port = self::getServer('SERVER_PORT');
        $port = ($port == '80') ? '' : (':' . $port);
        $server_name = self::getServer('SERVER_NAME');
        $current_url = $protocol . '://' . $server_name . $port . $req_uri;
        return $urlencode ? rawurlencode($current_url) : $current_url;
    }

    /**
     * 获得服务器本地ip
     *
     * @return string
     */
    private static function getServerIp() {
        $exec = "/sbin/ifconfig | grep 'inet addr' | awk '{ print $2 }' | awk -F ':' '{ print $2}' | head -1";
        $fp = @popen($exec, 'r');
        $ip = trim(@fread($fp, 2096));
        @pclose($fp);
        if (preg_match('/^[0-9\.]+$/', $ip)) {
            return $ip;
        } else {
            return '1.1.1.1';
        }
    }

    /**
     * Method  getSapi
     *
     * @static
     * @return string
     */
    private static function getSapi() {
        if (function_exists('php_sapi_name')) {
            return php_sapi_name();
        } else if (defined(PHP_SAPI)) {
            return PHP_SAPI;
        } else {
            return 'unknown';
        }
    }
}

class TraceId {
    const BIT_B64 = 'N2';
    const BIT_B32 = 'N';
    const BIT_B16 = 'n';
    const BIT_B16_SIGNED = 's';
    const BIT_B8 = 'C';

    public static function encode($idc, $ip, $time, $ms, $rand) {
        $init = self::pack(self::BIT_B64, 0x0000000000000000);
        $idc = self::pack(self::BIT_B64, $idc << 62);
        $ip = self::pack(self::BIT_B64, $ip << 46);
        $time = self::pack(self::BIT_B64, $time << 18);
        $ms = self::pack(self::BIT_B64, $ms << 8);
        $rand = self::pack(self::BIT_B64, $rand);
        $init |= $idc;
        $init |= $ip;
        $init |= $time;
        $init |= $ms;
        $init |= $rand;

        $traceid = self::unpack(self::BIT_B64, $init);
        return $traceid;
    }

    /**
     * @param $traceid
     * @return array
     */
    public static function decode($traceid) {
        // 4 => 8
        $rand = self::pack(self::BIT_B64, $traceid);
        $mask = self::pack(self::BIT_B64, 0x00000000000000ff);
        $rand = self::unpack(self::BIT_B64, $mask & $rand);
        // 14 => 10
        $ms = self::pack(self::BIT_B64, $traceid >> 8);
        $mask = self::pack(self::BIT_B64, 0x00000000000003ff);
        $ms = self::unpack(self::BIT_B64, $mask & $ms);
        // time 28
        $time = self::pack(self::BIT_B64, $traceid >> 18);
        $mask = self::pack(self::BIT_B64, 0x000000000fffffff);
        $time = self::unpack(self::BIT_B64, $mask & $time);
        // ip 16
        $ip = self::pack(self::BIT_B64, $traceid >> 46);
        $mask = self::pack(self::BIT_B64, 0x000000000000ffff);
        $ip = self::unpack(self::BIT_B64, $mask & $ip);
        // idc 2
        $idc = self::pack(self::BIT_B64, $traceid >> 62);
        $mask = self::pack(self::BIT_B64, 0x0000000000000003);
        $idc = self::unpack(self::BIT_B64, $mask & $idc);
        return array(
            'rand' => $rand,
            'ms' => $ms,
            'time' => $time,
            'ip' => $ip,
            'idc' => $idc,
        );
    }

    public static function Khex2bin($string) {
        if (function_exists('\hex2bin')) {
            return \hex2bin($string);
        } else {
            $bin = '';
            $len = strlen($string);
            for ($i = 0; $i < $len; $i += 2) {
                $bin .= pack('H*', substr($string, $i, 2));
            }
            return $bin;
        }
    }

    public static function unpack($type, $bytes) {
        if ($type == self::BIT_B64) {
            $set = unpack($type, $bytes);
            $original = ($set[1] & 0xFFFFFFFF) << 32 | ($set[2] & 0xFFFFFFFF);
            return $original;
        } else {
            return unpack($type, $bytes);
        }
    }

    public static function pack($type, $data) {
        if ($type == self::BIT_B64) {
            if ($data == -1) { // -1L
                $data = self::Khex2bin('ffffffffffffffff');
            } elseif ($data == -2) { // -2L
                $data = self::Khex2bin('fffffffffffffffe');
            } else {
                $left = 0xffffffff00000000;
                $right = 0x00000000ffffffff;
                $l = ($data & $left) >> 32;
                $r = $data & $right;
                $data = pack($type, $l, $r);
            }
        } else {
            $data = pack($type, $data);
        }
        return $data;
    }
}
