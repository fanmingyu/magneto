<?php

namespace Group\Common\Library;

class Curl
{

    const DEFAULT_USER_AGENT = 'group-common-curl';

    private $timeout = 3;

    private $ch = null;

    /**
     * 请求结果信息 (包括耗时、错误码等信息)
     */
    public $resultInfo = array();

    public static function instance()
    {
        return new self();
    }

    private function __construct()
    {
        $this->ch = curl_init();
    }

    /**
     * 设置超时时间
     */
    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Get请求
     */
    public function get($url)
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_USERAGENT, self::DEFAULT_USER_AGENT);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);

        return $this->curlExec();
    }

    /**
     * Post请求
     */
    public function post($url, $data)
    {
        if (!is_string($data)) {
            $data = http_build_query($data);
        }
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_USERAGENT, self::DEFAULT_USER_AGENT);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        return $this->curlExec();
    }

    public function setOpt($option, $value)
    {
        curl_setopt($this->ch, $option, $value);
        return $this;
    }

    private function curlExec()
    {
        $start = microtime(true);
        $result = curl_exec($this->ch);

        $this->resultInfo['cost'] = round(microtime(true) - $start, 5);
        $this->resultInfo['errno'] = curl_errno($this->ch);
        $this->resultInfo['error'] = curl_error($this->ch);
        $this->resultInfo['code'] = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        return $result;
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

}
