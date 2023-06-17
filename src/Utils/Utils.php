<?php

namespace App\Utils;

class Utils
{
    public static function mapToKey($array, $key): array
    {
        $result = [];
        foreach ($array as $obj) {
            $result[$obj[$key]] = $obj;
        }

        return $result;
    }
}