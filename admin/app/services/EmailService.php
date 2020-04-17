<?php
namespace Group\Magneto\Admin\Service;

use Group\Magneto\Plugins\Mail;

class EmailService
{

    const SMTP_SERVER_HOST = 'smtp.juren.com';
    const SMTP_SERVER_PORT =25;
    const SMTP_USER_MAIL = 'jrmail@juren.com';
    const SMTP_USER = 'jrmail@juren.com';
    const SMTP_PASS = 'JRmail@8888.';
    /**
     * sendSync
     * 同步邮件发送
     *
     * @param  array $receivers   收拾人
     * @param  mixed $subject     主题
     * @param  mixed $content     邮件内容
     * @return bool
     */
    public function sendSync(array $receivers, $subject, $content)
    {
        if(!$this->_isAllowedSend($subject))
        {
            return false;
        }
        $smtpemailto = implode(',',$receivers);//发送给谁,多个逗号分隔
        $smtp = new Mail(self::SMTP_SERVER_HOST,self::SMTP_SERVER_PORT,self::SMTP_USER,self::SMTP_PASS);
        $state = $smtp->sendmail($smtpemailto, self::SMTP_USER_MAIL, $subject, $content, "HTML");
//        $smtp->debug = false;
//        if($state){
//            echo "恭喜！邮件发送成功！！";
//        }else{
//            echo "对不起，邮件发送失败！请检查邮箱填写是否有误。";
//        }
        return true;
    }

    /**
     * @param $subject
     * 根据主题，防止邮件重复发送。
     * @return bool
     */
    private function _isAllowedSend($subject, $ttlSec = 300)
    {
        $cacheKey = 'magneto_' . md5('sendEmail_' . $subject);
        return getDI()->get('redis')->set($cacheKey, mt_rand(1, 100), array('nx', 'ex' => $ttlSec));
    }
}
