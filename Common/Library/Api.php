<?php

namespace Group\Common\Library;

use Group\Common\Library\TraceSdk;

class Api {

    //接口重试次数
    const REQUEST_API_RETRY_COUNT = 3;

    //请求超时时间(秒)
    const CURL_POST_TIMEOUT = 10;

    private static $instance = array();
    private static $ch;
    protected $interfaceName = '';

    /**
     * 单例化
     */
    public static function instance($apiType) {
        if (!isset(self::$instance[$apiType])) {
            self::$instance[$apiType] = new self($apiType);
        }

        return self::$instance[$apiType];
    }

    private $api = null;

    private $config = array();

    private function __construct($apiType) {
        $this->config = getDI()->getConfig()->api->$apiType;
        $className = '\Group\Common\Library\Api'.ucfirst($apiType).'Lib';
        $this->api = new $className($this->config);
    }

    /**
     * 动态配置api的gatewayUrl
     * @param string $key 接口关键字
     * @param string $url 请求的url地址
     * @return $this
     */
    public function gateway($key, $url, $isAppend = false) {
        if ($isAppend) {
            $this->config[$key]['appendUrl'] = $url;
        } else {
            if ($this->config['enableGateway'] && isset($this->config['gatewayUrl'])) {
                // 如果配置网关地址，则更新
                $this->config['gatewayUrl'] = $url;
            } else {
                $this->config[$key]['url'] = $url;
            }
        }

        return $this;
    }

    /**
     * 接口请求
     * @param string $key 接口关键字
     * @param array $parameters 参数数组
     * @param array $specialHeaders 特殊头信息参数数组
     * @param bool $keepalive 是否keepalive
     * @param int $timeout 请求超时时间
     * @return array
     */
    public function request($key, array $parameters, $specialHeaders = [], $keepalive = true, $timeout = false) {
        $url = $this->getRequestGateway($key);
        if (empty($url)) {
            TraceSdk::record(TraceSdk::LOG_TYPE_ERROR, __FILE__, __LINE__, "rpc", 'Request invalid gateway URI. key:'.$key);
            throw new \Exception('Request invalid URI');
        }

        $params = $this->api->generateParams($parameters, $key);
        TraceSdk::record(TraceSdk::LOG_TYPE_INFO, __FILE__, __LINE__, "rpc", [
            'msg'=>'Request start',
            'key'=>$key,
            'url'=>$url,
            'params'=>$parameters
        ]);

        // 发起请求
        $retryCount = empty($this->config[$key]['retry']) ? self::REQUEST_API_RETRY_COUNT : 1;
        for ($i = 0; $i < $retryCount; $i++) {
            if ($i > 0) {
                TraceSdk::record(TraceSdk::LOG_TYPE_ERROR, __FILE__, __LINE__, "rpc", 'Request retry '.$key.' '.$i.' times');
            }

            if ($timeout === false) {
                $timeout = empty($this->config[$key]['timeout'])
                    ? self::CURL_POST_TIMEOUT
                    : $this->config[$key]['timeout'];
            }

            $response = $this->post($url, $params, $error, $httpCode, $cost,
                $specialHeaders, $keepalive, $timeout);

            if (!empty($response)) {
                break;
            }
        }

        if (empty($response)) {
            TraceSdk::record(TraceSdk::LOG_TYPE_ERROR, __FILE__, __LINE__, "rpc", [
                'msg'=>'Request failed. Response is empty',
                'key'=>$key,
                'code'=>$httpCode,
                'error'=>$error,
                'url'=>$url
            ]);

            throw new \Exception('Response is empty');
        }

        // 解密结果
        $result = $this->api->decode($response);
        if (empty($result)) {
            TraceSdk::record(TraceSdk::LOG_TYPE_ERROR, __FILE__, __LINE__, "rpc", [
                'msg'=>'Request failed. Response decode failed',
                'key'=>$key,
                'code'=>$httpCode,
                'error'=>$error,
                'url'=>$url,
                'ret'=>$response
            ]);

            throw new \Exception('Response decode failed');
        }

        TraceSdk::record(TraceSdk::LOG_TYPE_INFO, __FILE__, __LINE__, "rpc", ['Request success', $result]);
        return $result;
    }

    /**
     * 回复加密数据
     * @param mixed $params
     * @return string
     */
    public function response($params) {
        TraceSdk::record(TraceSdk::LOG_TYPE_TRACE, __FILE__, __LINE__, "rpc", ['Callback response', $params]);
        return $this->api->encode($params);
    }

    /**
     * 验证参数签名
     */
    public function verify(array $params) {
        return $this->api->verify($params);
    }

    /**
     * POST请求
     */
    private function post($url, $params, &$error, &$httpCode, &$cost, $headers = [],
        $keepalive = true, $timeout = 3) {
        $digPoint = TraceSdk::digLogStart(__FILE__, __LINE__, 'rpc');

        if (!self::$ch) {
            $ch = curl_init();
            self::$ch = $ch;
        } else {
            $ch = self::$ch;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (substr($url, 0, 5) === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  //信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //检查证书中是否设置域名
        }

        $nextRpcIdHeader = TraceSdk::getCurlChildCallParam($digPoint);
        $headers = array_merge($nextRpcIdHeader, $headers);
        // 干死某些不要脸的服务提供者，application/z-xxxxx 是啥
        if (!empty($this->api->specialHeaders)) {
            $headers = array_merge($headers, $this->api->specialHeaders);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $requestStart = microtime(true);
        $result = curl_exec($ch);

        $cost = round(microtime(true) - $requestStart, 3);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        TraceSdk::digCurlEnd($digPoint, $url, 'post', $params, '', curl_getinfo($ch), $errno, $error, $result);
        if (!$keepalive) {
            curl_close($ch);
        }

        return $result;
    }

    /**
     * 拼接请求服务地址
     * @param string $key
     */
    public function getRequestGateway($key) {
        if (!empty($this->config['gatewayUrl'])) {
            $gatewayUrl = $this->config['gatewayUrl'].$this->getService($key);
        } else {
            $gatewayUrl = $this->config[$key]['url'];
            if (isset($this->config[$key]['appendUrl'])) {
                $gatewayUrl .= '/'.trim($this->config[$key]['appendUrl'], '/');
            }
        }

        if (isset($this->config['enableGateway']) && $this->config['enableGateway']) {
            $gatewayUrl = $this->config['gateway'].$this->getService($key);
        }

        if (empty($gatewayUrl)) {
            TraceSdk::record(TraceSdk::LOG_TYPE_ERROR, __FILE__, __LINE__, "rpc", 'Request invalid gateway for interfaceName:'.$key);
            throw new \Exception('Request invalid gateway.');
        }

        return $gatewayUrl;
    }

    public function getService($key) {
        $service = isset($this->config[$key]['service']) ? $this->config[$key]['service'] : null;
        if (empty($service)) {
            TraceSdk::record(TraceSdk::LOG_TYPE_ERROR, __FILE__, __LINE__, "rpc", 'Request with empty service config for interfaceName:'.$key);
            throw new \Exception('Request invalid service name');
        }
        return $service;
    }
}
