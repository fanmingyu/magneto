<?php

namespace Group\Magneto\Admin\Controllers\Api;

use Group\Magneto\Plugins\Weixin;
use Group\Magneto\Plugins\Sms;

class WeixinController extends ApiBaseController
{

    const NOTICE_APPID = 'notice';

    /**
     * 签名验证
     */
    protected $signatureEnable = false;

    /**
     * 发送文本消息
     */
    public function sendTextAction()
    {
        $to = isset($_REQUEST['to']) ? trim($_REQUEST['to']) : '';
        $content = isset($_REQUEST['content']) ? trim($_REQUEST['content']) : '';
        $appId = isset($_REQUEST['appId']) ? trim($_REQUEST['appId']) : self::NOTICE_APPID;
        $sms = isset($_REQUEST['sms']) ? intval($_REQUEST['sms']) : 0;

        if (empty($to) || empty($content)) {
            $this->error(-1, '参数错误');
        }

        $result = Weixin::instance($appId)->sendText($to, $content);
        if ($sms === 1) {
            Sms::instance()->sendToAccount(explode('|', $to), $content);
        }

        $this->success('', array('result' => $result));
    }

    /**
     * 消息总线消费者
     */
    public function msgbusConsumerAction()
    {
        // {"Topic":"fortest","WorkerId":2,"Times":0,"Timestamp":1527759743882774729,"Message":"one msg"}
        $input = file_get_contents('php://input');
        $params = json_decode($input, true);
        parse_str($params['Message'], $message);

        $result = Weixin::instance(self::NOTICE_APPID)->sendText($message['to'], $input);

        if (strpos($message['content'], 'RETRY') !== false) {
            $this->error(-1, 'retry');
        } else {
            $this->success();
        }
    }

}
