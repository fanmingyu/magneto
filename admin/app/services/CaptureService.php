<?php

namespace Group\Magneto\Admin\Service;

use Group\Common\Library\Logger;
use Group\Common\Library\Curl;


/**
 * 截图服务
 */
class CaptureService
{

    public function post($params = array(), $timeout)
    {
        $url = getDI()->get('config')->capture->host;
        $curl = Curl::instance();
        $result = $curl->setTimeout($timeout)->setOpt(CURLOPT_HTTPHEADER, array("Content-type: application/json"))->post($url, json_encode($params));
        $resultArray = json_decode($result, true);
        $paramsJson = json_encode($params);

        Logger::info("CapturePost. params:{$paramsJson}, result:{$result}");
        return $resultArray;
    }

}
