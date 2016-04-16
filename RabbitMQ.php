<?php

namespace Aidaojia\Common;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class RabbitMQ
{
    // 唯一实例
    protected static $_instance = null;

    protected $_config = [];

    protected $_connect = null;
    protected $_channel = null;
    protected $_queue   = '';

    public static function getInstance($queue = 'cache')
    {
        if ( ! isset(self::$_instance[$queue])) {
            self::$_instance[$queue] = new self($queue);
        }

        return self::$_instance[$queue];
    }

    public function __construct($queue = 'cache')
    {
        if ( ! $this->_config) {
            $this->_config = [
                'host' => env('RABBITMQ_HOST'),
                'port' => env('RABBITMQ_PORT'),
                'user' => env('RABBITMQ_USER'),
                'password' => env('RABBITMQ_PASSWORD'),
                'queue' => [
                    'cache' => env('RABBITMQ_QUEUE_CACHE'),
                    'log'   => env('RABBITMQ_QUEUE_LOG'),
                    'sms'   => env('RABBITMQ_QUEUE_SMS'),
                    'push'  => env('RABBITMQ_QUEUE_PUSH'),
                ]
            ];
        }

        if ( ! isset($this->_config['queue'][$queue])) {
            Log::error('Queue config not found: '. $queue);
            throw new \Exception('Queue config not found: '. $queue);
        }

        try {
            $this->_connect = new AMQPStreamConnection(
                $this->_config['host'],
                $this->_config['port'],
                $this->_config['user'],
                $this->_config['password']
            );

            $this->_queue = $this->_config['queue'][$queue];

            $this->_channel = $this->_connect->channel();
            $this->_channel->queue_declare($this->_queue, false, true, false, false);
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception($e->getMessage());
        }

        if ( ! $this->_connect) {
            Log::error('can not connect to rabbitmq server.');
            throw new \Exception('can not connect to rabbitmq server.');
        }
    }

    public function cache($table, $id = 0, $method = '', $params = [])
    {
        $msg = [
            'eventType'   => 'cache',
            'eventObject' => json_encode([
                'table'  => $table,
                'id'     => $id,
                'method' => $method,
                'params' => $params ?: (new \stdClass())
            ], JSON_UNESCAPED_UNICODE),
            'eventTime'   => get_milli_second()
        ];

        $this->_publish($msg);
    }

    public function log($level, $message, $trace)
    {
        static $flag = '';

        if ( ! $flag) {
            $flag = md5(rand(0, 100000000) * microtime());
        }

        $msg = [
            'eventType'   => 'php_log',
            'eventObject' => json_encode([
                'level'   => $level,
                'message' => $message ?: '',
                'trace'   => $trace ?: '',
                'flag'    => $flag
            ], JSON_UNESCAPED_UNICODE),
            'eventTime'   => get_milli_second()
        ];

        $this->_publish($msg);
    }

    public function push($to, $data, $model = '', $app = 'main')
    {
        if ( ! $to or ! $data or ! $data['title'] or ! $data['content']) {
            return false;
        }

        $to = is_array($to) ? $to : [$to];

        if ( ! isset($data['display'])) {
            $data['display'] = true;
        }

        $msg = [
            'eventType'   => 'push',
            'eventObject' => json_encode([
                'app'     => $app,
                'alias'   => $to,
                'model'   => $model,
                'title'   => $data['title'],
                'display' => (isset($data['display']) and $data['display']) ? true : false,
                'content' => $data['content'],
                'type'    => $data['type'] ?? '',
                'action'  => $data['action'] ?? '',
                'sound'   => $data['sound'] ?? 'default',
            ], JSON_UNESCAPED_UNICODE),
            'eventTime'   => get_milli_second()
        ];

        $this->_publish($msg);
    }

    public function sms($to, $content)
    {
        if ( ! $to or ! $content) {
            return false;
        }

        $to = is_array($to) ? $to : [$to];

        $msg = [
            'eventType'   => 'sms',
            'eventObject' => json_encode([
                'receivers' => $to,
                'content'   => $content,
            ], JSON_UNESCAPED_UNICODE),
            'eventTime'   => get_milli_second()
        ];

        $this->_publish($msg);
    }

    private function _publish($msg)
    {
        try {
            Log::debug('mq', $msg);

            $msg = new AMQPMessage(
                json_encode($msg, JSON_UNESCAPED_UNICODE),
                ['content_type' => 'text/json']
            );

            $this->_channel->basic_publish($msg, '', $this->_queue);
            unset($msg);
        }
        catch (\Exception $e) {
            Log::error($e);
        }
    }

    public function close()
    {
        $this->_channel->close();
        $this->_connect->close();
    }

    public function __destruct()
    {
        $this->close();
    }
}
