<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Common\Library\HttpLib;
use Group\Magneto\Plugins\Rc4crypt;

class SsoController extends \Phalcon\Mvc\Controller
{

    const WHO_SSO_API = 'http://sso.corp.com/public/loginsso';

    const WHO_SSO_SITE_NAME = 'magneto';

    const WHO_SSO_SIGN_KEY = '84b787';

    const WHO_SSO_CR4_KEY = 'adb389';

    /**
     * 登录请求
     */
    public function loginAction()
    {
        $url = isset($_GET['url']) ? urlencode($_GET['url']) : '';

        $redirectUrl = "http://{$_SERVER['HTTP_HOST']}/sso/check?url={$url}";
        $params = array(
            'ti' => time(),
            'fr' => self::WHO_SSO_SITE_NAME,
            're' => $redirectUrl,
            'to' => self::WHO_SSO_SITE_NAME,
        );
        $params['si'] = $this->signature($params, self::WHO_SSO_SIGN_KEY);

        $url = self::WHO_SSO_API.'?'.http_build_query($params);

        HttpLib::redirect($url);
    }

    /**
     * 登录完成检查
     */
    public function checkAction()
    {
        $url = isset($_GET['url']) ? trim($_GET['url']) : '';
        $username = isset($_GET['us']) ? trim($_GET['us']) : '';
        $time = isset($_GET['ti']) ? trim($_GET['ti']) : '';
        $token = isset($_GET['to']) ? trim($_GET['to']) : '';
        $signature = isset($_GET['si']) ? trim($_GET['si']) : '';

        //签名校验
        $params = array(
            'us' => $username,
            'ti' => $time,
            'to' => $token,
        );
        if (!$this->verifySignature($params, $signature, self::WHO_SSO_SIGN_KEY)) {
            exit('签名错误');
        }

        //时间校验(只在线上校验)
        if (ENV === 'online' && abs($time - time()) > 300) {
            exit('时间错误. local_time:'.date('Y-m-d H:i:s').', who_time:'.date('Y-m-d H:i:s', $time));
        }

        //解密用户名
        $rc4 = new Rc4crypt();
        $rc4->setKey(self::WHO_SSO_CR4_KEY);
        $username = $rc4->decrypt($username);

        session_start();
        $_SESSION['username'] = $username;
        $_SESSION['status'] = 1;
        session_write_close();

        HttpLib::redirect($url);
    }

    /**
     * 退出
     */
    public function logoutAction()
    {
        session_start();
        $_SESSION['status'] = 0;
        session_write_close();
    }

    private function signature($params, $signKey)
    {
        ksort($params);
        $sign = md5(implode('', $params).$signKey);
        return $sign;
    }

    private function verifySignature($params, $sign, $key)
    {
        ksort($params);
        $verify_sign = md5(implode('', $params).$key);
        return $verify_sign === $sign ? true : false;
    }

}
