<?php

namespace Group\Magneto\Plugins;

use Group\Common\Library\Logger;
use Group\Common\Library\Curl;

/**
 * 企业微信功能封装
 */
class Weixin
{

    const API_GET_TOKEN = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=%s&corpsecret=%s';

    const API_SEND_MESSAGE = 'https://qyapi.weixin.qq.com/cgi-bin/message/send';

    const API_GET_USER_LIST = 'https://qyapi.weixin.qq.com/cgi-bin/user/simplelist';

    const API_UPLOAD_MEDIA = 'https://qyapi.weixin.qq.com/cgi-bin/media/upload';

    const API_GET_MEDIA = 'https://qyapi.weixin.qq.com/cgi-bin/media/get';

    const API_GET_USER = 'https://qyapi.weixin.qq.com/cgi-bin/user/get';

    const API_CREATE_USER = 'https://qyapi.weixin.qq.com/cgi-bin/user/create';

    const API_UPDATE_USER = 'https://qyapi.weixin.qq.com/cgi-bin/user/update';

    const API_GET_APP_LIST = 'https://qyapi.weixin.qq.com/cgi-bin/agent/list';

    const API_GET_APP_INFO = 'https://qyapi.weixin.qq.com/cgi-bin/agent/get';

    const API_GET_DEPARTMENT_LIST = 'https://qyapi.weixin.qq.com/cgi-bin/department/list';

    const DEPARTMENT_ID = 4;

    const REQUEST_TIMEOUT = 3;

    private static $appsConfig = array(
        //通讯录
        'contacts' => array(
            'corpId' => 'wwf92ff9c44636c3cd',
            'agentId' => 1,
            //管理组密钥 (此密钥仅在旧版企业号中存在)
            'secret' => 'GJaXWv5MkFlD-Z35taRBP0uA46RKg3hscOetyCIuvek',
        ),
        //监控
        'monitor' => array(
            'corpId' => 'wwf92ff9c44636c3cd',
            'agentId' => 1000002,
            'secret' => '7lu61KSHNl1_bYuhCeUHwuSfQ67LASA4gEdwQjeW5kU',
        ),
        //告警
        'alarm' => array(
            'corpId' => 'wwf92ff9c44636c3cd',
            'agentId' => 1000003,
            'secret' => 'LbZ9B6ASen-qyUVEoErV81Jgto48S8EWR68dYNZvg_w',
        ),
        //公司企业号-告警
        'corp-alarm' => array(
            'corpId' => 'sadaso03kdksadh',
            'agentId' => 1000005,
            'secret' => 'sadf30dfjaljash94jdjrju85749fjgm',
        ),
        //公司企业号-监控
        'corp-monitor' => array(
            'corpId' => 'skeckxkjfjcedjk',
            'agentId' => 1000006,
            'secret' => 'sadfffff340582-495823iemvmmcfjkk',
        ),
    );

    private static $instance = array();

    /**
     * 单例化
     */
    public static function instance($appName)
    {
        if (!isset(self::$instance[$appName])) {
            if (empty(self::$appsConfig[$appName])) {
                throw new \Exception('微信应用配置不存在');
            }

            $config = self::$appsConfig[$appName];
            self::$instance[$appName] = new self($config['corpId'], $config['agentId'], $config['secret']);
        }

        return self::$instance[$appName];
    }

    private $corpId = '';

    private $agentId = '';

    private $secret = '';

    private function __construct($corpId, $agentId, $secret)
    {
        $this->corpId = $corpId;
        $this->agentId = $agentId;
        $this->secret = $secret;
    }

    private $token = '';

    /**
     * 获取AccessToken
     * AccessToken参数由CorpID和Secret换取
     */
    private function getToken()
    {
        if ($this->token !== '') {
            return $this->token;
        }

        $url = sprintf(self::API_GET_TOKEN, $this->corpId, $this->secret);

        $curl = Curl::instance();
        $result = $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false)->setOpt(CURLOPT_SSL_VERIFYHOST, false)->get($url);

        $result = json_decode($result, true);

        $this->token = !empty($result['access_token']) ? $result['access_token'] : '';
        Logger::info("WeixinGetToken. agentId:{$this->agentId}, corpId:{$this->corpId}, token:".substr($this->token, 0, 10).'****');

        return $this->token;
    }

    /**
     * 向微信发起get请求
     */
    private function get($apiUrl, $params = array())
    {
        $url = $apiUrl.'?access_token='.$this->getToken();
        $url .= '&'.http_build_query($params);

        $curl = Curl::instance();
        $result = $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false)->setOpt(CURLOPT_SSL_VERIFYHOST, false)->get($url);

        $resultArray = json_decode($result, true);

        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
        Logger::info("WeixinGet. url:{$apiUrl}, params:{$paramsJson}, result:{$result}");

        return $resultArray;
    }

    /**
     * 向微信发起post请求
     */
    private function post($apiUrl, $params = array())
    {
        $url = $apiUrl.'?access_token='.$this->getToken();
        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);

        $curl = Curl::instance();
        $result = $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false)->setOpt(CURLOPT_SSL_VERIFYHOST, false)->post($url, $paramsJson);

        $resultArray = json_decode($result, true);

        Logger::info("WeixinPost. url:{$apiUrl}, params:{$paramsJson}, result:{$result}");

        return $resultArray;
    }

    /**
     * 发送文本消息
     * @params mix $to 支持字符串或数组
     */
    public function sendText($to, $content)
    {
        $params = array(
            'touser' => is_array($to) ? implode('|', $to) : $to,
            'msgtype' => 'text',
            'agentid' => $this->agentId,
            'text' => array(
                'content' => "{$content}"
            ),
        );

        return $this->post(self::API_SEND_MESSAGE, $params);
    }

    /**
     * 上传临时素材
     * @params $content 素材内容 $type 类型：image、voice、file、video
     */
    private function uploadMedia($content, $type)
    {
        $url = self::API_UPLOAD_MEDIA.'?access_token='.$this->getToken()."&type={$type}";
        $curl = Curl::instance();
        $curl->setOpt(CURLOPT_HTTPHEADER, array(
            'Content-Type: application/octet-stream',
        ));
        $result = $curl->post($url, $content);
        $result = json_decode($result, true);

        if ($result['errcode'] != 0) {
            Logger::info("WeiXinUploadMedia fail. type:{$type}, result:{$result}");
            return null;
        }

        return $result;
    }

    /**
     * 获取临时素材
     * @params $mediaId 素材ID，由uploadMedia返回
     */
    private function getMedia($mediaId)
    {
        $params = array(
            'media_id' => $mediaId,
        );

        return $this->get(self::API_GET_MEDIA, $params);
    }

    /**
     * 发送图片
     * @params $to 发送者 $imageUrl 图片文件路径
     */
    public function sendImage($to, $imageUrl)
    {
        $result = $this->uploadMedia($imageUrl, 'image');
        if ($result == null) {
            return;
        }

        $params = array(
            'touser' => is_array($to) ? implode('|', $to) : $to,
            'msgtype' => 'image',
            'agentid' => $this->agentId,
            "image" => array(
                "media_id" => $result['media_id'],
            ),
        );

        return $this->post(self::API_SEND_MESSAGE, $params);
    }

    /**
     * 获取用户列表
     */
    public function getUserList()
    {
        $params = array(
            'department_id' => 1,
            'fetch_child' => 1,
            'status' => 1,
        );

        return $this->get(self::API_GET_USER_LIST, $params);
    }

    /**
     * 获取用户信息
     */
    public function getUser($userId)
    {
        $result = $this->get(self::API_GET_USER, array('userid' => $userId));
        if ($result['errcode'] != 0) {
            Logger::info(__FUNCTION__ . " fail. params:{$userId}, result:" . json_encode($result));
            return null;
        }
        return $result;
    }

    /**
     * 创建用户
     */
    public function createUser($userId, $name, $mobile, $departmentId)
    {
        $params = array(
            'userid' => $userId,
            'name' => $name,
            'department' => $departmentId > 0 ? $departmentId : self::DEPARTMENT_ID,
            'mobile' => $mobile,
        );

        return $this->post(self::API_CREATE_USER, $params);
    }

    /**
     * 更新用户
     */
    public function updateUser($userId, $name, $mobile, $departmentId)
    {
        $params = array(
            'userid' => $userId,
            'name' => $name,
            'mobile' => $mobile,
        );

        if ($departmentId > 0) {
            $params['department'] = array($departmentId);
        }

        return $this->post(self::API_UPDATE_USER, $params);
    }

    /**
     * 获取应用列表
     */
    public function getAppList()
    {
        return $this->get(self::API_GET_APP_LIST);
    }

    /**
     * 获取单个应用信息
     */
    public function getAppInfo($agentId)
    {
        $params = array(
            'agentid' => $agentId,
        );

        return $this->get(self::API_GET_APP_INFO, $params);
    }

    /**
     * 获取部门列表
     */
    public function getDepartmentList()
    {
        $department = array();

        $result = $this->get(self::API_GET_DEPARTMENT_LIST);
        foreach ($result['department'] as $item) {
            $department[$item['id']] = $item;
        }

        return $department;
    }

}
