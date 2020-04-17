<?php

namespace Group\Magneto\Admin\Controllers\Api;

use Group\Common\Library\SignatureLib;
use Group\Common\Library\Logger;

class ApiBaseController extends \Phalcon\Mvc\Controller
{

    /**
     * 是否进行签名验证
     */
    protected $signatureEnable = true;

    /**
     * 计算签名是否进行urlencode
     */
    protected $urlencode = false;

    /**
     * 默认签名密钥
     */
    protected $signatureKey = '2421a3d1aa83fa1c019727139940a03d';

    public function initialize()
    {
        $data['request'] = $_REQUEST;
        $data['json'] = file_get_contents("php://input");
        Logger::info("Backend Api Request Start. Request:" . json_encode($data, JSON_UNESCAPED_UNICODE));
        //签名校验
        $params = $_REQUEST;
        unset($params['_url']);

        if ($this->signatureEnable && SignatureLib::verify($params, $this->signatureKey, 'sign', $this->urlencode) !== true) {
            $this->error(-100, '签名错误');
        }
    }

    protected function error($code, $message)
    {
        $this->responseJson($code, $message, array());
    }

    protected function success($message = '请求成功', $data = array())
    {
        $this->responseJson(0, $message, $data);
    }

    /**
     * 返回Json格式结果
     */
    private function responseJson($code, $message, $data)
    {
        header('Content-Type:application/json');
        $result = json_encode(array(
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ), JSON_UNESCAPED_UNICODE);
        Logger::info("Backend Api Request End. Response:" . $result);
        echo $result;
        exit();
    }

}
