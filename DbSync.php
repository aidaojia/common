<?php

declare(strict_types=1);

namespace Aidaojia\Common;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Aidaojia\Common\RabbitMQ as MQClient;

/**
 * Class DbSync
 *
 * @package App\Foundation\Commons
 */
class DbSync
{
    private static $_instance = null;

    // 同步标示
    private $_sync = false;
    private $_client = null;

    /**
     * @return DbSync|null
     */
    public static function getInstance()
    {
        if ( ! self::$_instance) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * DbSync constructor.
     */
    public function __construct()
    {
        $this->_sync = env('DB_SYNC', false);
        if ($this->_sync) {
            try {
                $this->_client = MQClient::getInstance('cache');
            }
            catch(\Exception $e) {
                $this->_sync = false;
            }
        }
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return bool
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if ( ! $this->_sync) return false;

        $matches = [];
        switch($name) {
            case 'isInsert':
                preg_match('/insert\s+into\s+(.*)/i', $arguments[0], $matches);
                break;
            case 'isUpdate':
                preg_match('/update\s+(.+)\s+set\s+(.*)/i', $arguments[0], $matches);
                break;
            case 'isDelete':
                preg_match('/delete\s+from\s+(.+)/i', $arguments[0], $matches);
                break;
            default:
                throw new \Exception('call method not found.');
        }

        return $matches ? true : false;
    }

    public function hookUpdate($query, $bindings)
    {
        if ( ! $this->_sync) return true;

        preg_match('/update\s+(.+)\s+set\s+(.*)/i', $query, $matches);

        $table = $matches[1];
        $where = '';
        $set   = $matches[2];

        preg_match('/(.*)\s+where\s+(.*)/i', $set, $matches);
        if ($matches) {
            $set   = $matches[1];
            $where = $matches[2];
        }
        unset($set);

        $table = $this->_parseTableName($table);
        if ($table[1] != null) {
            $where = str_replace($table[1]. '.', '', $where);
        }
        $table = $table[0];

        $where = $where ?: '1';

        $bindingsNum = substr_count($where, '?');
        if ($bindingsNum and $bindingsNum < count($bindings)) {
            $bindings = array_slice($bindings, -$bindingsNum, $bindingsNum);
        }

        $list = DB::select("select * from {$table} where {$where}", $bindings);

        // 提交任务到RMQ
        if ($table != ''  and $list) {
            $table = str_replace('`', '', $table);

            if (env('APP_DEBUG')) {
                Log::info('update', [$table, $list]);
            }

            foreach ($list as $v) {
                $v = object_to_array($v);

                $this->_client->cache($table, $v['id'], 'update', $v);
            }
        }

        return true;
    }

    public function hookDelete($query, $bindings)
    {
        if ( ! $this->_sync) return true;

        preg_match('/delete\s+from\s+(.+)/i', $query, $matches);
        if ( ! $matches) return true;

        $delete = $matches[1];
        preg_match('/(.+)\s+where\s+(.+)/i', $delete, $matches);

        if ( ! $matches) {
            throw new \Exception('SQL 错误, Delete 不允许没有where条件: '. $query);
        }

        $table = $matches[1];
        $where = $matches[2];

        $table = $this->_parseTableName($table);
        if ($table[1] != null) {
            $where = str_replace($table[1]. '.', '', $where);
        }
        $table = $table[0];

        $bindingsNum = substr_count($where, '?');
        if ($bindingsNum and $bindingsNum < count($bindings)) {
            $bindings = array_slice($bindings, -$bindingsNum, $bindingsNum);
        }

        $list = DB::select("select * from {$table} where {$where}", $bindings);

        // 提交任务到RMQ
        if ($table != '' and $list) {
            $table = str_replace('`', '', $table);

            if (env('APP_DEBUG')) {
                Log::info('delete', [$table, $list]);
            }

            foreach ($list as $v) {
                $v = object_to_array($v);

                $this->_client->cache($table, $v['id'], 'delete', $v);
            }
        }

        return true;
    }

    /**
     * @param $query
     * @param int $id
     * @param int $affected
     *
     * @return bool
     */
    public function hookInsert($query, $id = 0, $affected = 1)
    {
        if ( ! $this->_sync) return true;

        preg_match('/insert\s+into\s+(.[^\(]*)\s+(\(.*?\)\s+)?values\s+(\(.*\))/i', $query, $matches);

        if ( ! $matches) {
            preg_match('/insert\s+into\s+(.+)\s+set\s+(.*)/i', $query, $matches);

            if ( ! $matches) {
                return true;
            }
        }

        $table = $matches[1];
        $table = str_replace('`', '', $table);
        $table = $this->_parseTableName($table)[0];

        $ids  = [$id];
        if ($affected > 1) {
            $i = $id - $affected + 1;
            for (; $i < $id; $i++) {
                $ids[] = $i;
            }
        }

        if (env('APP_DEBUG')) {
            Log::info('insert', [$table, $ids]);
        }

        foreach ($ids as $id) {
            $this->_client->cache($table, $id, 'insert');
        }

        return true;
    }

    private static function _parseTableName($table)
    {
        $table = trim($table);
        if (strpos($table, ' ') === false) {
            return [$table, null];
        }

        return explode(' ', $table);
    }

    /**
     * @param $query
     * @param $bindings
     *
     * @return mixed
     */
    private function _parseSql($query, $bindings)
    {
        foreach ($bindings as $v) {
            $v = is_numeric($v) ? $v : '"'. $v. '"';
            $query = $this->_replaceOnce('?', $v, $query);
        }

        return $query;
    }

    /**
     * @param $needle
     * @param $replace
     * @param $haystack
     *
     * @return mixed
     */
    private function _replaceOnce($needle, $replace, $haystack)
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }
}