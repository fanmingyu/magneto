<?php

namespace Group\Magneto\Plugins;

class ViewUtil
{

    /**
     * 过滤敏感数据
     */
    public static function stripSensitive($value)
    {
        $value = preg_replace('/\'(\d{8})(\d{6})/', '\'$1********', $value);
        $value = preg_replace('/\'(\d{3})(\d{4})(\d{4})/', '\'$1****$3', $value);
        return $value;
    }

}
