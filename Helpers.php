<?php

declare(strict_types=1);

use Illuminate\Contracts\Cookie\Factory as CookieFactory;

/**
 * @param int $code
 * @param array $data
 * @param string $message
 * @param int $status
 *
 * @return \Symfony\Component\HttpFoundation\Response
 */
function response_json(int $code = 0, array $data = [], string $message = '', int $status = 200)
{
    if ( ! $message) {
        switch ($code) {
            case -1:
                $message = '系统错误'; break;
            case -2:
                $message = 'Token错误'; break;
            case -3:
                $message = 'API不存在'; break;
            case -4:
                $message = '非法操作'; break;
            case -5:
                $message = '没有权限操作'; break;
            case -6:
                $message = '参数错误'; break;
        }
    }

    return response()->json(['code' => $code, 'data' => $data, 'msg'  => $message], $status);
}

/**
 * 获取数据库配置
 *
 * @return array
 */
function get_database_config()
{
    $_DBConfig = env('DB_SEPARATE_RW')
        ? [
            'read' => [
                'host' => env('DB_READ_HOST', 'localhost'),
                'port' => env('DB_READ_PORT', 3306)
            ],
            'write' => [
                'host' => env('DB_WRITE_HOST', 'localhost'),
                'port' => env('DB_WRITE_PORT', 3306)
            ],
        ]
        : [
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306)
        ];

    return array_merge([
        'driver'    => 'mysql',
        'database'  => env('DB_DATABASE', 'forge'),
        'username'  => env('DB_USERNAME', 'forge'),
        'password'  => env('DB_PASSWORD', ''),
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => env('DB_PREFIX', ''),
        'timezone'  => env('DB_TIMEZONE', '+00:00'),
        'strict'    => false,
    ], $_DBConfig);
}

/**
 * 配置文件路径
 */
if ( ! function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param  string $path
     * @return string
     */
    function config_path($path = '')
    {
        return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
    }
}

if ( ! function_exists('cookie')) {
    /**
     * Create a new cookie instance.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  int     $minutes
     * @param  string  $path
     * @param  string  $domain
     * @param  bool    $secure
     * @param  bool    $httpOnly
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    function cookie($name = null, $value = null, $minutes = 0, $path = null, $domain = null, $secure = false, $httpOnly = true)
    {
        $cookie = app(CookieFactory::class);

        if (is_null($name)) {
            return $cookie;
        }

        return $cookie->make($name, $value, $minutes, $path, $domain, $secure, $httpOnly);
    }
}

/**
 * 简化input
 */
if ( ! function_exists('input')) {
    function input($name, $default = null)
    {
        return \Illuminate\Support\Facades\Request::input($name, $default);
    }
}


/**
 * Generate an asset path for the application.
 *
 * @param  string  $path
 * @param  bool    $secure
 * @return string
 */
if (! function_exists('asset')) {
    function asset($path, $secure = null)
    {
        return app('url')->asset($path, $secure);
    }
}

/**
 * 调试函数
 */
if ( ! function_exists('ddp')) {
    function ddp(...$items)
    {
        echo '<pre>';
        foreach ($items as $item) {
            print_r($item);
        }
        exit('</pre>');
    }
}

/**
 * alias table
 */
if ( ! function_exists('alias_table')) {
    function alias_table($obj, $aliasTable)
    {
        return $obj . ' as ' . $aliasTable;
    }
}

/**
 * object_to_array
 */
if ( ! function_exists('object_to_array')) {
    function object_to_array($obj)
    {
        if (is_object($obj)) {
            $obj = get_object_vars($obj);
        }

        if (is_array($obj)) {
            return array_map(__FUNCTION__, $obj);
        }

        return $obj;
    }
}

/**
 * get_milli_second
 */
if ( ! function_exists('get_milli_second')) {
    function get_milli_second()
    {
        list($t1, $t2) = explode(' ', microtime());

        return sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }
}

/**
 * @param $str
 *
 * @return string
 */
if ( ! function_exists('md5_16')) {
    function md5_16($str)
    {
        return substr(md5($str), 8, 16);
    }
}


/**
 * @param $str
 * @param bool|true $urlEncoded
 *
 * @return mixed
 */
if ( ! function_exists('remove_invisible_chars')) {
    function remove_invisible_chars($str, $urlEncoded = true)
    {
        $notDisplayAbles = [];

        if ($urlEncoded) {
            $notDisplayAbles[] = '/%0[0-8bcef]/';   // url encoded 00-08, 11, 12, 14, 15
            $notDisplayAbles[] = '/%1[0-9a-f]/';    // url encoded 16-31
        }

        $notDisplayAbles[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';    // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($notDisplayAbles, '', $str, -1, $count);
        }
        while ($count);

        return $str;
    }
}

/**
 * download_transcode
 */
if ( ! function_exists('download_transcode')) {
    function download_transcode($str)
    {
        static $platform = null;
        if ($platform == null) {
            $platform = strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mac') !== false ? 'mac' : 'win';
        }

        if (is_array($str)) {
            return array_map('download_transcode', $str);
        }

        if ($platform == 'mac') {
            return $str;
        }

        return iconv('UTF-8', 'GB2312//IGNORE', $str);
    }
}
