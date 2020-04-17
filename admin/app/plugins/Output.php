<?php

namespace Group\Magneto\Plugins;

class Output
{

    public static function numberFormat($number, $decimals = 2)
    {
        return is_float($number) ? number_format($number, $decimals) : number_format($number);
    }

}
