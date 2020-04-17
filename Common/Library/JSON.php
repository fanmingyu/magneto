<?php
namespace Group\Common\Library;

class JSON
{
    public static function encode($obj)
    {
        return json_encode($obj);
    }

    public static function decode($json, $toAssoc = false)
    {
        // 抑制错误，以异常形式吐出
        $result = @json_decode($json, $toAssoc);
        $error = '';
        switch(json_last_error()) {
            case JSON_ERROR_DEPTH:
                $error =  ' - Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = ' - Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = ' - Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = ' - Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $error = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            case JSON_ERROR_NONE:
            default:
                $error = '';
        }

        if (!empty($error)) {
            throw new JSONException('JSON Error '.$error);
        }

        return $result;
    }
}

class JSONException extends \Exception
{

}
