<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Common\Library\Logger;

require APP_ROOT_DIR.'/admin/app/plugins/weixinsdk/WXBizMsgCrypt.php';

/**
 * 微信接口Base类
 */
class BaseController extends \Phalcon\Mvc\Controller
{

    /**
     * 微信上行请求过来的全部数据
     */
    protected $request = null;

    /**
     * 微信请求过来的文本内容
     */
    protected $key = '';

    /**
     * 微信请求过来的内容切分数组
     */
    protected $keyArray = array();

    /**
     * 默认调度的方法
     */
    const DISPATCH_DEFAULT_METHOD = 'responseDefault';

    /**
     * 查看帮助关键字
     */
    const HELP_RULE_KEY = '帮助';

    public function initialize()
    {
        //微信回调接口验证
        if (isset($_GET['echostr'])) {
            return $this->responseEchostr();
        }

        $signature = isset($_GET['msg_signature']) ? $_GET['msg_signature'] : '';
        $timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : '';
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        $requestData = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';

        if (empty($requestData)) {
            exit('Invalid request');
        }

        $result = '';
        $errCode = $this->getWeixinCrypt()->DecryptMsg($signature, $timestamp, $nonce, $requestData, $result);
        if ($errCode !== 0) {
            $this->log("DecryptMsgFailed. errCode:{$errCode}, params:".json_encode($_GET).", data:{$requestData}");
            exit('DecryptMsgFailed');
        }

        $this->request = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->key = $this->request->Content;
        $this->keyArray = preg_split('/\s+/', $this->key, 10);

        $this->log('WeixinRequest. data:'.json_encode($this->request));

        //对事件消息不做处理
        if (empty($this->key)) {
            exit();
        }

        $method = $this->dispatchMethod();

        $this->log("WeixinDispatchMethod. dispatchMethod:{$method}");
        call_user_func(array($this, $method));
    }

    /**
     * 获取应该执行的函数
     */
    protected function dispatchMethod()
    {
        foreach ($this->rules as $rule => $method) {
            //正则匹配
            if (substr($rule, 0, 1) === '/' && $this->keyMatch($rule)) {
                return $method;
            //第一个关键词匹配
            } elseif ($this->firstKeyIs($rule)) {
                return $method;
            }
        }

        return self::DISPATCH_DEFAULT_METHOD;
    }

    /**
     * 微信官方加密SDK
     */
    private $weixinCrypt = null;

    protected function getWeixinCrypt()
    {
        if ($this->weixinCrypt === null) {
            $this->weixinCrypt = new \WXBizMsgCrypt($this->token, $this->aesKey, $this->corpId);
        }

        return $this->weixinCrypt;
    }

    /**
     * 默认回复
     */
    protected function responseDefault()
    {
        $content = $this->key === self::HELP_RULE_KEY ? '帮助' : '暂时不支持该输入。';
        $content .= "\n";
        $content .= implode("\n", $this->help);

        $this->responseText($content);
    }

    /**
     * 微信接口echostr验证
     */
    protected function responseEchostr()
    {
        $this->log("EchostrRequest. params:".json_encode($_GET));

        $signature = isset($_GET['msg_signature']) ? trim($_GET['msg_signature']) : '';
        $timestamp = isset($_GET['timestamp']) ? trim($_GET['timestamp']) : '';
        $nonce = isset($_GET['nonce']) ? trim($_GET['nonce']) : '';
        $echostr = isset($_GET['echostr']) ? trim($_GET['echostr']) : '';

        $result = '';
        $errCode = $this->getWeixinCrypt()->VerifyURL($signature, $timestamp, $nonce, $echostr, $result);
        if ($errCode !== 0) {
            $this->log("EchostrFailed. errCode:{$errCode}");
            echo 'Verify failed';
            exit();
        }

        $this->log("EchostrSuccess.");
        echo $result;
        exit();
    }

    /**
     * 回复文本消息
     */
    protected function responseText($content)
    {
        $content = trim(implode("\n", func_get_args()));
        $createTime = time();

        $sRespData = "<xml>
            <ToUserName><![CDATA[{$this->request->FromUserName}]]></ToUserName>
            <FromUserName><![CDATA[{$this->request->ToUserName}]]></FromUserName>
            <CreateTime>{$createTime}</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[{$content}]]></Content>
            <MsgId>{$this->request->MsgId}</MsgId>
            <AgentID>{$this->request->AgentID}</AgentID>
        </xml>";

        $result = '';
        $wxcpt = new \WXBizMsgCrypt($this->token, $this->aesKey, $this->corpId);
        $errCode = $wxcpt->EncryptMsg($sRespData, time(), mt_rand(0, 10000), $result);
        if ($errCode !== 0) {
            $this->log("EncryptMsgFailed. errCode:{$errCode}, data:{$result}");
            exit();
        }

        echo $result;
        $this->log("responseText. toUserName:{$this->request->ToUserName}, content:{$content}");
        exit();
    }

    /**
     * 正则匹配
     */
    private function keyMatch($pattern)
    {
        return preg_match($pattern, $this->key) ? true : false;
    }

    /**
     * 匹配第一个关键字
     */
    private function firstKeyIs($key)
    {
        return $this->keyArray[0] == $key ? true : false;
    }

    /**
     * 日志记录
     */
    protected function log($content)
    {
        $controller = $this->dispatcher->getControllerName();
        Logger::info("[WeixinAPI:{$controller}] $content");
    }

}
