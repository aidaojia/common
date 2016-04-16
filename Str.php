<?php

declare(strict_types=1);

namespace Aidaojia\Common;

class Str
{
    public static function replaceOnce(string $needle, string $replace, string $haystack): string
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }
}