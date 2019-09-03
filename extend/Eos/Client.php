<?php

namespace Eos;

use GuzzleHttp\Client as GuzzleHttp;
use think\Db;
use think\facade\Env;

class Client
{
    // GuzzleHttp
    protected $client = null;
    // Error
    protected $error = null;

    // Wif private keys
    protected $priKeys = [];

    // Plugin version
    public $version = 1;

    /**
     *
     */
    public function __construct($options = [])
    {
        $defaultOptions = [
            'base_uri' => 'http://52.199.180.232:8833',
            'timeout' => 100,
            'verify' => false,
        ];
        if (is_string($options)) {
            $this->client = new GuzzleHttp(array_merge($defaultOptions, ['base_uri' => $options]));
        } else {
            $this->client = new GuzzleHttp(array_merge($defaultOptions, $options));
        }
    }

    /**
     * 设置私钥
     */
    public function addPrivateKeys(array $priKeys)
    {
        $temp = [];
        foreach ($priKeys as $key => $value) {
            try {
                $temp[Ecc::privateToPublic($value)] = $value;

            } catch (\Exception $e) {
                throw new \Exception("$value Is Error Wif Private Key", 1);
            }
        }

        // 转账账户使用的 公钥 => 私钥
        $this->priKeys = array_unique(array_merge($this->priKeys, $temp));

        return $this;
    }

    // 获取需要私钥签名对应的公钥列表
    public function getRequiredKeys($transaction)
    {
        if (isset($transaction['expiration'])) {
            // 2018-11-28T09:23:21.000';
            $transaction['expiration'] = date('Y-m-d\TH:i:s.000', $transaction['expiration']);
        }

        return $this->chain()->getRequiredKeys([
            'transaction' => $transaction,
            'available_keys' => array_keys($this->priKeys),
        ]);
    }

    /**
     * 交易
     * int $uid 交易跟注册
     * $uid > 0 代表是交易转账
     */
    public function transaction(
        array $transaction,
        $blocksBehind   = 3,
        $expireSeconds  = 30,
        $uid            = 0
    ) {
        $chain = $this->chain();

        foreach ($transaction['actions'] as $key => $value) {
            $transaction['actions'][$key]['data'] = $chain->abiJsonToBin([
                'code'      => $value['account'],
                'action'    => $value['name'],
                'args'      => $value['data'],
            ])->binargs;
        }

        // 获取区块信息
        $info = $chain->getInfo();

        $block = $chain->getBlock([
            'block_num_or_id' => $info->head_block_num - $blocksBehind
        ]);

        // 设置交易过期时间，UTC时区
        $default = date_default_timezone_get();
        date_default_timezone_set('UTC');
        // 合并数据
        $transaction = array_merge([
            'expiration'            => strtotime($info->head_block_time) + $expireSeconds,
            'ref_block_num'         => $block->block_num & 0xffff,
            'ref_block_prefix'      => $block->ref_block_prefix,
            'max_net_usage_words'   => 0,
            'max_cpu_usage_ms'      => 0,
            'delay_sec'             => 0,
            'context_free_actions'  => [],
            'actions'               => [],
            'transaction_extensions'=> [],
        ], $transaction);

        // 序列化交易
        $st      = $this->serializeTransaction($transaction);
        $chainId = $info->chain_id;

        // 需要用来签名的公钥列表
        //转账将进行这里的代码
        if($uid > 0)
        {
            //$uid = 0;// 付款方 jm_members.uid

            // 从数据库获取付款方的私钥 jm_members.active_private
            $user = \think\Db::name('members')
                ->where('id', $uid)
                ->field('active_private')
                ->find();

            $requiredKeys = [rsa_decrypt($user['active_private'])];
        } else {
            //$requiredKeys = $this->getRequiredKeys($transaction)->required_keys;// 原码勿删

            $requiredKeys = [trim(rsa_decrypt(Env::get("block.blockchain_hash")))];
        }

        // 将时区设置会默认的
        date_default_timezone_set($default);
        // 签名
        $signatures = $this->signTransaction($chainId, $st, $requiredKeys);

        return $this->chain()->pushTransaction([
            'signatures'                => $signatures,
            'compression'               => 0,
            'packed_context_free_data'  => '',
            'packed_trx'                => $st,
        ]);
    }

    /**
     * 序列化交易
     * @return 返回序列化交易的十六进制
     */
    public function serializeTransaction(array $transaction)
    {
        return Serialize::transaction($transaction);
    }

    /**
     * 事物签名
     * @return Array
     */
    public function signTransaction($chainId, $st, $requiredKeys)
    {
        $packedContextFreeData = '0000000000000000000000000000000000000000000000000000000000000000';
        $signBuf = $chainId . $st . $packedContextFreeData;

        $signatures = [];
        foreach ($requiredKeys as $key => $value) {
            //$signatures[] = Ecc::sign($signBuf, $this->priKeys[$value]);
            $signatures[] = Ecc::sign($signBuf, $value);
        }

        return $signatures;
    }

    /**
     * Plugin chain rpc
     */
    public function chain()
    {
        return new Plugin("v{$this->version}/chain", $this);
    }

    /**
     * Plugin history rpc
     */
    public function history()
    {
        return new Plugin("v{$this->version}/history", $this);
    }

    /**
     * Plugin net rpc
     */
    public function net()
    {
        return new Plugin("v{$this->version}/net", $this);
    }

    /**
     * Plugin db_size rpc
     */
    public function dbSize()
    {
        return new Plugin("v{$this->version}/db_size", $this);
    }

    /**
     * Plugin db_size rpc
     */
    public function db_size()
    {
        return $this->dbSize();
    }

    /**
     * Plugin wallet rpc
     */
    public function wallet()
    {
        return new Plugin("v{$this->version}/wallet", $this);
    }

    /**
     * 设置版本
     * @return this
     */
    public function version($version)
    {
        if (strtolower(substr($version, 0, 1)) == 'v') {
            $this->version = substr($version, 1);
        } else {
            $this->version = $version;
        }
        return $this;
    }

    /**
     * 发出请求
     */
    public function request($path, $params = [])
    {
//        help_p('-- 发出请求 --');
//        help_p($path);
//        help_p($params);
//        help_p('-- 发出请求*结束 --');

        $data = [];
        if ($params)
        {
            $data['json'] = $params;
        }

        try {
            $res = $this->client->post($path, $data);

            return json_decode($res->getBody());
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse())
            {
                $error = json_decode($e->getResponse()->getBody());
                $this->error = $error;

                // Error Message
                $message = $error->error->what;

                if (isset($error->error->details))
                {
                    $message = $error->error->details[0]->message;
                }

                throw new \Exception($message, $error->code);
            } else {
                $this->error = null;
            }
        }

        return null;
    }// request() end

    /**
     * 获取响应错误详细信息
     */
    public function getError()
    {
        return $this->error;
    }
}
