<?php
/**
 * @annotate
 * @author 江枫
 * @email 635449961@qq.com
 * @url:www.cloudcmf.com
 * Date: 2017/3/10
 * Time: 10:43
 */

namespace system;

use think\facade\Env;

class Redis
{

    protected $redis;

    protected $options = null;

    protected static $instance = null;
    protected $project = null;

    /**
     * 架构函数
     * @param array $options 缓存参数
     * @access public
     */

    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        $this->options = array_merge([
            'host' => config("redis.host") ?: '127.0.0.1',
            'port' => config("redis.port") ?: 6379,
            'password' => config("redis.password") ?: '',
            'select' => config("redis.select") ?: 0,
            'timeout' => config("redis.timeout") ?: 0,
            'expire' => config("redis.expire") ?: 0,
            'persistent' => false], $options);

        $func = $this->options['persistent'] ? 'pconnect' : 'connect';
        $this->redis = new \Redis;
        $this->redis->$func($this->options['host'], $this->options['port'], $this->options['timeout']);
        $this->project = Env::get("project");
        if ('' != $this->options['password']) {
            $this->redis->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->redis->select($this->options['select']);
        }
    }

    /**
     * @annotate 单例
     * @author 江枫
     * @email 635449961@qq.com
     * @url:www.cloudcmf.com
     * @time
     */
    public static function instance($options = [])
    {
        if (!empty($options)) {
            return new self($options);
        }
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 判断redis
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        $name = $this->getKey($name);
        return $this->redis->get($name) ? true : false;
    }

    public function setKey($project = '')
    {
        if ($project) {
            return $this->project = $project;
        }

    }

    private function getKey($name)
    {
        return $this->project . ":" . $name;
    }

    /**
     * 读取redis数据
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $name = $this->getKey($name);
        $value = $this->redis->get($name);

        if (is_null($value)) {
            return $default;
        }

        $jsonData = json_decode($value, true);

        // 检测是否为JSON数据 true 返回JSON解析数组, false返回源数据
        return (null === $jsonData) ? $value : $jsonData;
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value 存储数据
     * @param integer $expire 有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        $name = $this->getKey($name);

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        //对数组/对象数据进行缓存处理，保证数据完整性
        $value = (is_object($value) || is_array($value)) ? json_encode($value) : $value;

        if (is_int($expire) && $expire) {
            $result = $this->redis->setex($name, $expire, $value);
        } else {
            $result = $this->redis->set($name, $value);
        }

        return $result;
    }


    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        $name = $this->getKey($name);
        //return $this->redis->delete($name);
        return $this->redis->del($name);
    }


    /**
     * 调用redis其它操作方法
     *
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public function __call($name, $params)
    {
        $name = $this->getKey($name);
        return call_user_func_array(array($this->redis, $name), $params);
    }
}