<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/17
 * Time: 12:33
 */

namespace service;

use Eos\Client;
use Eos\Ecc;
use think\Db;

class Block
{
    /**
     * 获取用户的ZING
     */
    public function getUserZing($mid, $cache = 0)
    {
        if (empty($mid)) {
            return 0;
        }
        if ($cache > 0) {
            $balance = cache("getUserZing:" . $mid);
            if ($balance) {
                return $balance;
            }
        }

        $address = getUserInfo($mid, 'address');

        if (empty($address)) {
            return 0;
        }

        $data = $this->get_token($address, 1);

        if ($data['balance'] > 0) {
            if ($cache > 0) {
                cache("getUserZing:" . $mid, $data['balance'], $cache);
            }
            return $data['balance'];
        } else {
            return 0;
        }
    }// getUserZing() end

    //转账
    // int $uid 是付款人 jm_members.id
    public function transaction($id = '', $uid = 0)
    {
//        help_p('转账操作');
//        help_p($id);

        $c_url  = config('site.transfer_url');
        $c_id   = config('site.account_id');
        $client = new Client($c_url);

        if (empty($id)) {
            return false;
        }

        // 获取用户转账记录
        $traccount = Db::name("sun_traccount")
                        ->where(array("id" => $id, "status" => 0))
                        ->find();

        if (empty($traccount)) {
            help_test_logs(['empty(traccount)', __LINE__]);
            return false;
        }

        $member     = dbMember("members")
                        ->where(array("id" => $traccount['mid']))
                        ->find();
        // 获取指定币种
        $currency   = Db::name("token_currency")
                        ->where(array("id" => $traccount['currency_id']))
                        ->find();

        if (empty($currency) || empty($member)) {
            help_test_logs(['empty(currency) || empty(member)', __LINE__]);
            return false;
        }

        // 新建账号
        $client->addPrivateKeys([
            rsa_decrypt($member['active_private'])
        ]);

        $ws             = $currency['length'];
        $account        = $member['address'];
        $to_address     = $traccount['address'];
        $company_wallet = dbMember("members")->where(array("id" => $c_id))->find();

        if ($to_address == $company_wallet['address']) {   //如果直接转给总账户 那么就不需要分开算手续费了
            $traccount['true_money'] = $traccount['money'];
            $traccount['poundage']   = 0;
        }

        $money      = number_format($traccount['true_money'], $ws, ".", "");
        $money      = $money . " " . $currency['name'];
        $type       = $currency['creator'];
        $poundage   = round($traccount['poundage'], $ws);

        if ($poundage > 0)
        {
            $fee_address    = $company_wallet['address'];
            $poundage       = number_format($poundage, $ws, ".", "") . " " . $currency['name'];

            $actions = [
                [
                    'account'       => $type,
                    'name'          => 'transfer',
                    'authorization' => [
                        [
                            'actor'      => $account,
                            'permission' => 'active',
                        ]
                ],
                'data' => [
                    'from' => $account,
                    'to' => $to_address,
                    'quantity' => $money,
                    'memo' => $traccount['remark'],
                ],
            ], [
                'account'       => $type,
                'name'          => 'transfer',
                'authorization' => [
                    [
                        'actor' => $account,
                        'permission' => 'active',
                    ]
                ],
                'data' => [
                    'from'      => $account,
                    'to'        => $fee_address,
                    'quantity'  => $poundage,
                    'memo'      => "fee",
                ],
            ]];
        } else {
            $actions = [[
                'account' => $type,
                'name'    => 'transfer',
                'authorization' => [
                    [
                        'actor'      => $account,
                        'permission' => 'active',
                    ]
                ],
                'data'    => [
                    'from'      => $account,
                    'to'        => $to_address,
                    'quantity'  => $money,
                    'memo'      => $traccount['remark'],
                ],
            ]];
        }

        try {
            $tx = $client->transaction([
                'actions' => $actions
            ], 3, 30, $uid);

            $data                   = [];
            $data['transaction_id'] = $tx->transaction_id;
            $data['block_num']      = $tx->processed->block_num;
            $data['status']         = 1;

            return $data;
        } catch (\Exception $e) {
            help_test_logs(['链上转账记录-失败', $e->getMessage()]);
            return $e->getMessage();
        }
    }// transaction() end

    public function red_traccout($id = ''){
        $c_url = config('site.transfer_url');
        $c_id = env("account.gather");
        $client = new Client($c_url);
        if (empty($id)) {
            return false;
        }
        $red_packet = Db::name("red_packet_traccout")->where(array("id" => $id, "status" => 0))->find();
        if (empty($red_packet)) {
            return false;
        }
        $member = dbMember("members")->where(array("mobile" => $red_packet['mobile']))->find();
        if(empty($member)){
            return false;
        }
        $currency = Db::name("token_currency")->where(array("id" =>1))->find();
        if (empty($currency) || empty($member)) {
            return false;
        }
        // 转账
        $company_wallet = dbMember("members")->where(array("id" => $c_id))->find();
        $account = $company_wallet['address'];
        $client->addPrivateKeys([
            rsa_decrypt($company_wallet['active_private'])
        ]);
        $ws = $currency['length'];
        $to_address = $member['address'];
        $money = number_format($red_packet['money'], $ws, ".", "");
        $money = $money . " " . $currency['name'];
        $type = $currency['creator'];
        $actions = [[
            'account' => $type,
            'name' => 'transfer',
            'authorization' => [[
                'actor' => $account,
                'permission' => 'active',
            ]],
            'data' => [
                'from' => $account,
                'to' => $to_address,
                'quantity' => $money,
                'memo' => "red",
            ],
        ]];
        try {
            $tx = $client->transaction([
                'actions' => $actions
            ]);
            $data = array();
            $data['transaction_id'] = $tx->transaction_id;
            $data['block_num'] = $tx->processed->block_num;
            $data['status'] = 1;
            return $data;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    //红包转账
    public function red_transaction($id = '')
    {
        $c_url  = config('site.transfer_url');
        $c_id   = env("account.gather");
        $client = new Client($c_url);

        if (empty($id))
        {
            return false;
        }
        $red_packet = Db::name("red_packet")
                        ->where(array("id" => $id, "status" => 0))
                        ->find();

        if (empty($red_packet))
        {
            return false;
        }

        $member = dbMember("members")
                    ->where(array("id" => $red_packet['mid']))
                    ->find();

        $currency = Db::name("token_currency")
                        ->where([
                            "id" => $red_packet['currency_id']
                        ])
                        ->find();

        if (empty($currency) || empty($member)) {
            return false;
        }

        // 转账
        $client->addPrivateKeys([
            rsa_decrypt($member['active_private'])
        ]);

        $ws             = $currency['length'];
        $account        = $member['address'];
        $company_wallet = dbMember("members")->where(array("id" => $c_id))->find();
        $to_address     = $company_wallet['address'];
        $money          = number_format($red_packet['money'], $ws, ".", "");
        $money          = $money . " " . $currency['name'];
        $type           = $currency['creator'];

        $actions = [[
            'account'       => $type,
            'name'          => 'transfer',
            'authorization' => [
                [
                    'actor'      => $account,
                    'permission' => 'active',
                ]
            ],
            'data'          => [
                'from'      => $account,
                'to'        => $to_address,
                'quantity'  => $money,
                'memo'      => "red",
            ],
        ]];

        try {
            $tx = $client->transaction([
                'actions' => $actions
            ]);

            $data                   = array();
            $data['transaction_id'] = $tx->transaction_id;
            $data['block_num']      = $tx->processed->block_num;
            $data['status']         = 1;

            return $data;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }// red_transaction() end

    //获取区块链转账记录
    public function get_transfer_list($uid, $page = 1, $currency)
    {
        set_time_limit(0);

        if (empty($uid)) {
            return false;
        }

        $user      = getUserInfo($uid);
        $where     = array("id" => $currency);
        $currencys = Db::name("token_currency")
                        ->where($where)
                        ->find();

        if (!empty($user['address']))
        {
            while (1) {
                $c_url = config("site.transfer_list");
                $url   = $c_url . '/action?name=' . $user['address'] . '&token=' . $currencys['creator'] . '&size=100&page=' . $page;

                $data = curl()->get($url);

                $list = json_decode($data, true);
             
                if (!empty($list['items']))
                {
                    foreach ($list['items'] as $k => $v) {
                        $da                     = array();
                        $da['transaction_id']   = $v['trx_id'];
                        $tran_data              = $v['act']['data'];

                        if ($tran_data['to'] == $user['address'])
                        {
                            $address = $tran_data['from'];
                            $type    = 2;
                        } else {
                            $address = $tran_data['to'];
                            $type    = 1;
                        }

                        $gyb_log = Db::name("sun_traccount")
                                    ->where([
                                        "transaction_id" => $v['trx_id'],
                                        "address"        => $address, "mid" => $uid
                                    ])
                                    ->find();

                        if (!empty($gyb_log)) {
                            break;
                        }

                        $quan = explode(" ", $tran_data['quantity']);

                        $data = [
                            'ordersn' => makeOrderNo("GM"),
                            'mid' => $uid,
                            "transaction_id"=> $v['trx_id'],
                            "block_num"     => empty($v['block_num']) ? "--" : $v['block_num'],
                            "address"       => $address,
                            'money'         => $quan[0] * 1,
                            'true_money'    => $quan[0] * 1,
                            'currency_id'   => $currency,
                            'type'          => $type,
                            'status'        => 1,
                            "is_app"        => 0,
                            'remark'        => $tran_data['memo'],
                            'create_time'   => (strtotime($v['block_time']) + 3600)
                        ];

                        Db::name("sun_traccount")->insert($data);
                    }
                } else {
                    break;
                }

                $page = $page + 1;
            }
        }
    }// get_transfer_list() end

    //获取账户余额
    public function get_token($account, $id = '')
    {
        $c_url = config('site.transfer_url');
        $client = new Client($c_url);


        $chain = $client->chain();
        $where = array("status" => 1);

        if (!empty($id)) {
            $where['id'] = $id;
        }

        $tokens =  Db::name("token_currency")
                    ->field("id as currency_id,name,image,creator,price")
                    ->where($where)
                    ->order('num', "ASC")
                    ->select();

        $data = [];

        foreach ($tokens as $k => $token) {

            // 报错：$chain->get_currency_balance()
            $res = $chain->get_currency_balance(['code' => $token['creator'], 'symbol' => $token['name'], "account" => $account]);

            if (!empty($res)) {
                $cc = explode(" ", $res[0]);
                $token['balance'] = ($cc[0] * 1) . "";
                $token['balance_price'] = round($cc[0] * $token['price'], 2) . "";
            } else {
                $token['balance'] = "0";
                $token['balance_price'] = "0";
            }

            $token['image'] = empty($token['image']) ? '' : cmf_get_image_preview_url($token['image']);
            $data[] = $token;
        }

        if (!empty($id)) {
            if (!empty($data)) {
                return $data[0];
            }
        }

        return $data;
    }
}