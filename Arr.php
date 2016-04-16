<?php

declare(strict_types=1);

namespace Aidaojia\Common;

/**
 * Class Arr
 *
 * @package App\Foundation\Commons
 */
class Arr
{
    /**
     * @param array $array
     * @param string $key
     *
     * @return array
     */
    public static function toKeyArray(array $array, string $key): array
    {
        $result = array();
        foreach ($array as $v) {
            if (isset($v[$key])) {
                $result[$v[$key]] = $v;
            }
        }

        return $result;
    }
}