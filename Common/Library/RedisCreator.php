<?php
namespace Group\Common\Library;

/**
 * RedisCreator redis 工厂
 * redis扩展 接口文档 见https://github.com/nicolasff/phpredis.
 */
class RedisCreator
{

    public static function getRedis($redisConfigArr,$timeout = 1, $pconnect = false, $db = '')
    {
        static $ins = array();

        $key = md5(serialize($redisConfigArr)).$db;
        if (isset($ins[$key])) {
            return $ins[$key];
        }

        $ins[$key] = self::createAndConnectRedis($redisConfigArr,$timeout, $pconnect);

        return $ins[$key];
    }

    public static function createAndConnectRedis($redisConfigArr, $timeout = 1, $pconnect = false)
    {

        $masterInfo = self::getMasterInfo($redisConfigArr);
        $redis = new \Redis();
        if (!$pconnect) {
            $redis->connect($masterInfo['host'], $masterInfo['port'], $timeout);
        } else {
            $redis->pconnect($masterInfo['host'], $masterInfo['port'], $timeout);
        }
        if(isset($masterInfo['password']) && !empty($masterInfo['password'])){
            $redis->auth($masterInfo['password']);
        }
        return $redis;
    }

    private static function getMasterInfo($redisConfigArr)
    {
        $redis = new \Redis();
        foreach ($redisConfigArr as $redisConf) {
            //单点模式
            if($redisConf['type'] == 'single') {
                return $redisConf;
            }
            //哨兵模式
            $timeOutSec = isset($redisConf['timeOutSec']) ? $redisConf['timeOutSec'] : 1;
            try {

                if ($redis->connect($redisConf['host'], $redisConf['port'], $timeOutSec)) {
                    $info = $redis->info('Sentinel');
                    if (preg_match('#address=(?<ip>[\d,.]+):(?<port>\d+),#', $info['master0'], $matches)) {
                        return array('host' => $matches['ip'], 'port' => $matches['port']);
                    }
                }
            } catch (\Exception $e) {
                Logger::info(implode('|', [__METHOD__, $e->getMessage()]));
            }
        }
        throw new \Exception('Redis全挂了');
    }

}

