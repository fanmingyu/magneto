<?php
namespace Group\Common\Library;

/**
 * AES对称加密封装 (包括base64)
 * 算法: AES128位
 * 模式: ECB
 * 填充: PKCS5Padding
 */
class AesLib
{
    public static function StrToHex($string)
    {
        $hex="";
        for   ($i=0;$i<strlen($string);$i++)
            $hex.=dechex(ord($string[$i]));
        $hex=strtoupper($hex);
        return   $hex;
    }

    public static function HexToStr($hex)
    {
        $string="";
        for   ($i=0;$i<strlen($hex)-1;$i+=2)
            $string.=chr(hexdec($hex[$i].$hex[$i+1]));
        return   $string;
    }


    /**
     * AES Key转换
     */
    public static function aesKeyConvert($key)
    {
        $result = '';

        $keyLen = strlen($key);
        for ($i = 0; $i < $keyLen; $i += 2)
        {
            $result .= chr('0x'.$key[$i].$key[$i + 1]);
        }

        return $result;
    }

    /**
     * 加密方法
     * @param string $input
     * @return string
     */
    public static function EncodeWithOpenssl($input, $key)
    {
        //AES, 128 模式加密数据 ECB
        $md5key = strtoupper(md5($key));
        $key = hex2bin($md5key);
        $encrypted = openssl_encrypt($input,'AES-128-ECB',$key);
        return $encrypted;
    }


    /**
     * 解密方法
     * @param string $encrypted
     * @return string
     */
    public static function DecodeWithOpenssl($encrypted, $key)
    {
        //AES, 128 模式加密数据 ECB
        $md5key = strtoupper(md5($key));
        $key = hex2bin($md5key);
        $decrypted = openssl_decrypt($encrypted,'AES-128-ECB',$key);
        return $decrypted;
    }

    /**
     * 加密 (Aes + base64)
     */
    public static function encode($input, $key)
    {
        $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
        $input = self::pkcs5Padding($input, $size);

        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $data = mcrypt_generic($td, $input);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $data = base64_encode($data);

        return $data;
    }

    /**
     * PKCS5方式填充
     */
    private static function pkcs5Padding($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text.str_repeat(chr($pad), $pad);
    }

    /**
     * 解密 (base64 + Aes)
     */
    public static function decode($data, $key)
    {
        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, base64_decode($data), MCRYPT_MODE_ECB);

        $dec_s = strlen($decrypted);
        $padding = ord($decrypted[$dec_s - 1]);
        $decrypted = substr($decrypted, 0, -$padding);

        return $decrypted;
    }

    /**
     * _hex2bin
     *
     * @param mixed $hex
     * @static
     * @access public
     * @return void
     */
    public static function _hex2bin($hex = false){
        $ret = $hex !== false && preg_match('/^[0-9a-fA-F]+$/i', $hex) ? pack("H*", $hex) : false;
        return $ret;
    }



}
