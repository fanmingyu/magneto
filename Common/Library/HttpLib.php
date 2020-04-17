<?php
namespace Group\Common\Library;

class HttpLib
{

    /**
     * 获得当前Client IP
     *
     * @return string ip值
     */
    public static function getClientIp()
    {
        global $_SERVER;
        if (isset($_SERVER['Cdn_Src_Ip']) && !empty($_SERVER['Cdn_Src_Ip'])) {
            return $_SERVER['Cdn_Src_Ip'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
            for ($i = 0, $total = count($ips); $i < $total; $i++) {
                if (!preg_match("/^(10|172.16|192.168)./i", $ips[$i])) {
                    return $ips[$i];
                }
            }
        }

        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER["HTTP_CLIENT_IP"])) {
            return $_SERVER["HTTP_CLIENT_IP"];
        }
        
        return '127.0.0.1';
    }

    /**
     * 将IP转化为数字
     *
     * @param string $ip
     * @return number
     */
    public static function ip2Int($ip)
    {
        // 分隔ip段
        $ip_arr = explode('.', $ip);

        foreach ($ip_arr as $value) {
            // 将每段ip转换成16进制
            $iphex = dechex($value);
            // 255的16进制表示是ff，所以每段ip的16进制长度不会超过2

            if (strlen($iphex) < 2) {
                // 如果转换后的16进制数长度小于2，在其前面加一个0
                $iphex = '0' . $iphex;

                // 没有长度为2，且第一位是0的16进制表示，这是为了在将数字转换成ip时，好处理
            }

            // 将四段IP的16进制数连接起来，得到一个16进制字符串，长度为8
            $ipstr .= $iphex;
        }

        // 将16进制字符串转换成10进制，得到ip的数字表示
        return hexdec($ipstr);
    }

    /**
     * 将数字转化为IP
     *
     * @param number $n
     * @return string
     */
    public static function int2Ip($n)
    {
        // 将10进制数字转换成16进制
        $iphex = dechex($n);
        // 得到16进制字符串的长度
        $len = strlen($iphex);

        if (strlen($iphex) < 8) {
            // 如果长度小于8，在最前面加0
            $iphex = '0' . $iphex;
            // 重新得到16进制字符串的长度
            $len = strlen($iphex);
        }

        // 这是因为ipton函数得到的16进制字符串，如果第一位为0，在转换成数字后，是不会显示的
        // 所以，如果长度小于8，肯定要把第一位的0加上去
        // 为什么一定是第一位的0呢，因为在ipton函数中，后面各段加的'0'都在中间，转换成数字后，不会消失
        for ($i = 0, $j = 0; $j < $len; $i = $i + 1, $j = $j + 2) {
            // 循环截取16进制字符串，每次截取2个长度
            $ippart = substr($iphex, $j, 2); // 得到每段IP所对应的16进制数
            $fipart = substr($ippart, 0, 1); // 截取16进制数的第一位
            if ($fipart == '0') {
                // 如果第一位为0，说明原数只有1位
                $ippart = substr($ippart, 1, 1); // 将0截取掉
            }
            $ip[] = hexdec($ippart); // 将每段16进制数转换成对应的10进制数，即IP各段的值
        }
        $ip = array_reverse($ip);

        // 连接各段，返回原IP值
        return implode('.', $ip);
    }

    /**
     * 重定向到某个url
     *
     * @param string $url
     *            某个Url
     */
    public static function redirect($url)
    {
        Header("Location: " . $url);
    }

    public static function isMobileAccess()
    {
        // 先检查是否为wap代理，准确度高
        if (array_key_exists('HTTP_VIA', $_SERVER) && stristr($_SERVER['HTTP_VIA'], "wap")) {
            return true;
        } elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML") > 0) {
            // 检查浏览器是否接受 WML.
            return true;
        } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            // 检查USER_AGENT
            return true;
        } else {
            return false;
        }
    }

    // add by wangjiansong@
    // copy from P2P
    public static function getHttp()
    {
        global $_SERVER;
        return (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) ? 'https://' : 'http://';
    }

    public static function getDomain()
    {
        global $_SERVER;
        /* 协议 */
        $protocol = self::getHttp();

        /* 域名或IP地址 */
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            /* 端口 */
            if (isset($_SERVER['SERVER_PORT'])) {
                $port = ':' . $_SERVER['SERVER_PORT'];
                if ((':80' == $port && 'http://' == $protocol) || (':443' == $port && 'https://' == $protocol)) {
                    $port = '';
                }
            } else {
                $port = '';
            }
            if (isset($_SERVER['SERVER_NAME'])) {
                $host = $_SERVER['SERVER_NAME'] . $port;
            } elseif (isset($_SERVER['SERVER_ADDR'])) {
                $host = $_SERVER['SERVER_ADDR'] . $port;
            }
        }
        return $protocol . $host;
    }

    public static function getHost()
    {
        global $_SERVER;
        $host = '';
        /* 域名或IP地址 */
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            if (isset($_SERVER['SERVER_NAME'])) {
                $host = $_SERVER['SERVER_NAME'];
            } elseif (isset($_SERVER['SERVER_ADDR'])) {
                $host = $_SERVER['SERVER_ADDR'];
            }
        }
        return $host;
    }
}
