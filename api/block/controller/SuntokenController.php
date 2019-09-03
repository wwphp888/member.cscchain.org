<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/16
 * Time: 15:43
 */

namespace api\block\controller;

use api\common\controller\ApiController;
use Eos\Client;
use Eos\Ecc;
use think\Db;
use think\facade\Env;

class SuntokenController extends ApiController
{

    //创建区块链地址
    public function create_account($mobile = '')
    {
        // 新建账号
        $c_url     = config('site.transfer_url');//这个地址是币地址还是某个公司的网址
        $c_private = trim(rsa_decrypt(Env::get("block.blockchain_hash")));//密文内容

        if (empty($mobile))
        {
            $data   = request()->param();
            $mobile = $data['mobile'];
        }
        if (empty($mobile)) {
            return $this->error(lang('error'));
        }

        $client = new Client($c_url);

        $member = dbMember('members')
            ->where("mobile", $mobile)
            ->find();

        if (empty($member)) {
            return $this->error(lang('error'));
        }
        if (!empty($member['address'])) {
            return $this->success(lang('success'));
        }

        $newAccount      = $this->get_account($mobile);
        $active_private  = Ecc::randomKey();
        $activePublicKey = Ecc::privateToPublic($active_private);
        $owner_private   = Ecc::randomKey();
        $ownerPublicKey  = Ecc::privateToPublic($owner_private);

        $data = [
            "address"           => $newAccount,
            "active_private"    => rsa_encrypt($active_private),
            "activePublicKey"   => $activePublicKey,
            "owner_private"     => rsa_encrypt($owner_private),
            "ownerPublicKey"    => $ownerPublicKey
        ];

        $tx = $client->addPrivateKeys([$c_private])->transaction([
            'actions' => [
                [
                    'account'       => 'eosio',
                    'name'          => 'newaccount',
                    'authorization' => [
                        [
                            'actor'      => 'eosio',
                            'permission' => 'active',
                        ]
                    ],
                    'data'          => [
                        'creator'   => 'eosio',
                        // Main net key is name
                        'name'      => $newAccount,
                        'owner'     => [
                            'threshold' => 1,
                            'keys'      => [
                                ['key' => $ownerPublicKey, 'weight' => 1],
                            ],
                            'accounts'  => [],
                            'waits'     => [],
                        ],
                        'active'        => [
                            'threshold' => 1,
                            'keys'      => [
                                ['key' => $activePublicKey, 'weight' => 1],
                            ],
                            'accounts'  => [],
                            'waits'     => [],
                        ],
                    ],
                ],
                [
                    'account'       => 'eosio',
                    'name'          => 'buyram',
                    'authorization' => [
                        [
                            'actor'      => 'eosio',
                            'permission' => 'active',
                        ]
                    ],
                    'data'          => [
                        'payer'     => 'eosio',
                        'receiver'  => $newAccount,
                        //'bytes'     => 40000,
                        'quant' => '20000.0000 SYS'
                    ],
                ],
                [
                    'account'       => 'eosio',
                    'name'          => 'delegatebw',
                    'authorization' => [
                        [
                            'actor'      => 'eosio',
                            'permission' => 'active',
                        ]
                    ],
                    'data'          => [
                        'from'               => 'eosio',
                        'receiver'           => $newAccount,
                        'stake_net_quantity' => '5000.0000 SYS',
                        'stake_cpu_quantity' => '5000.0000 SYS',
                        'transfer'           => 0,
                    ],
                ]
            ]
        ]);

        $data['transaction_id'] = $tx->transaction_id;

        if ($data['transaction_id'])
        {
            dbMember('members')
                ->where("mobile", $mobile)
                ->update($data);

            return $this->success(lang('success'));
        }
    }// create_account() end

 

  

    //转账
    public function transaction()
    {
        $c_url  = config('site.transfer_url');
        $c_id   = config('site.account_id');
        $client = new Client($c_url);
        $data   = $this->request->param();
        $id     = $data['id'];

        if (empty($id))
        {
            return $this->error(lang('error'));
        }

        $traccount = Db::name("sun_traccount")
            ->where([
                "id" => $id,
                "status" => 0
            ])
            ->find();

        if (empty($traccount))
        {
            return $this->error(lang('order_1'));
        }

        $member   = dbMember("members")
            ->where("id", $traccount['mid'])
            ->find();
        $currency = Db::name("token_currency")
            ->where("id", $traccount['currency_id'])
            ->find();

        // 新建账号
        $client->addPrivateKeys([
            rsa_decrypt($member['active_private'])
        ]);
        $ws             = $currency['length'];
        $account        = $member['address'];
        $to_address     = $traccount['address'];

        $company_wallet = dbMember("members")
            ->where(array("id" => $c_id))
            ->find();

        if ($to_address == $company_wallet['address'])
        {   //如果直接转给总账户 那么就不需要分开算手续费了
            $traccount['true_money'] = $traccount['money'];
            $traccount['poundage']   = 0;
        }

        $money    = number_format($traccount['true_money'], $ws, ".", "");
        $money    = $money . " " . $currency['name'];
        $type     = $currency['creator'];
        $poundage = round($traccount['poundage'], $ws);

        if ($poundage > 0)
        {
            $fee_address = $company_wallet['address'];
            $poundage    = number_format($poundage, $ws, ".", "") . " " . $currency['name'];

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
                    'data'              => [
                        'from'      => $account,
                        'to'        => $to_address,
                        'quantity'  => $money,
                        'memo'      => $traccount['remark'],
                    ],
                ], [
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
                        'to'        => $fee_address,
                        'quantity'  => $poundage,
                        'memo'      => "fee",
                    ],
                ]];
        } else {
            $actions = [
                [
                    'account'       => $type,
                    'name'          => 'transfer',
                    'authorization' => [[
                        'actor' => $account,
                        'permission' => 'active',
                    ]],
                    'data' => [
                        'from'      => $account,
                        'to'        => $to_address,
                        'quantity'  => $money,
                        'memo'      => $traccount['remark'],
                    ],
                ]];
        }
        $tx = $client->transaction([
            'actions' => $actions
        ]);

        $data                   = [];
        $data['transaction_id'] = $tx->transaction_id;
        $data['block_num']      = $tx->processed->block_num;

        return $this->success(lang('message_1'), $data);
    }


    public function get_account($mobile = '')
    {
        $random_array = [
            "q",
            "a",
            "z",
            "5",
            "w",
            "s",
            "x",
            "e",
            "d",
            "c",
            "1",
            "r",
            "f",
            "v",
            "t",
            "g",
            "b",
            "y",
            "h",
            "n",
            "2",
            "u",
            "j",
            "m",
            "4",
            "i",
            "k",
            "o",
            "3",
            "l",
            "p"
        ];

        $account    = "";
        $s_length   = 12;
        $i          = 0;

        while ($i < $s_length) {
            $ran      = mt_rand(0, 30);
            $account .= $random_array[$ran];
            $i++;
        }

        return $account;
    }
}