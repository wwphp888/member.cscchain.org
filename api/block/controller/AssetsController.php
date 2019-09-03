<?php
/**
 * Created by PhpStorm.
 */

namespace api\block\controller;


use api\common\controller\ApiUserController;
use api\common\model\LockedModel;
use service\Block;
use think\Db;

class AssetsController extends ApiUserController
{
    /**
     * 钱包首页
     */
    public function index()
    {
        $account =  Db::name("sun_account")
                    ->where("mid", $this->uid)
                    ->find();

        if(empty($account))
        {
            $account = [
                'mid'           => $this->uid,
                'usdt_balance'  => 0,
                'btc_balance'   => 0,
                'eos_balance'   => 0,
                'eth_balance'   => 0
            ];

            Db::name("sun_account")->insert($account);
        }

        $markerPrices   = get_price();
        $currency       = [];
        $usdt_value     = round($account['usdt_balance']*$markerPrices['usdt'],2);
        $btc_value      = round($account['btc_balance']*$markerPrices['btc_usdt'],2);
        $eth_value      = round($account['eth_balance']*$markerPrices['eth_usdt'],2);
        $eos_value      = round($account['eos_balance']*$markerPrices['eos_usdt'],2);

        $currency[] = [
            'id'        => 1,
            "name"      => 'USDT',
            'balance'   => ($account['usdt_balance']*1)."",
            'value'     => $usdt_value.""
        ];
        $currency[] = [
            'id'        => 2,
            "name"      => 'BTC',
            'balance'   => ($account['btc_balance']*1)."",
            'value'     => $btc_value.""
        ];
        $currency[] = [
            'id'        => 3,
            "name"      => 'ETH',
            'balance'   => ($account['eth_balance']*1)."",
            'value'     => $eth_value.""
        ];
        $currency[] = [
            'id'        => 4,
            "name"      => 'EOS',
            'balance'   => ($account['eos_balance']*1)."",
            'value'     => $eos_value.""
        ];

        $total_value        = round($usdt_value + $btc_value + $eth_value + $eos_value,2)."";
        $zing_num           = round($total_value/$markerPrices['zing_usdt'],2)."";
        $recharge_address   = Db::name("sun_recharge_address")->where("mid",$this->uid)->find();

        if(!empty($recharge_address)){
            $data['is_default'] = 1;
        }else{
            $data['is_default'] = 0;
        }

        $data['total_value']    = $total_value;
        $data['zing_num']       = $zing_num;
        $data['zing_price']     = $markerPrices['zing_usdt'];
        $data['currency']       = $currency;

        $this->success($data);
    }

    public function account_log($id = '',$page = 1)
    {
        $list = [];

        if(!empty($id)) {
            $list = Db::name("sun_account_log")
                        ->where([
                            "mid" => $this->uid,
                            "cid" => $id
                        ])
                        ->page($page, 10)
                        ->order('create_time desc')
                        ->select()
                        ->toArray();

            foreach ($list as $k => &$v) {
                $v['create_time'] = date("Y-m-d H:i:s", $v['create_time']);

                $str = $v['type'] == 1 ? "+" : "-";

                $v['money'] = $str.($v['money']*1);

                $v['type_name'] = $v['type'] == 1 ?"Recharge success " : "Invest mine success";

                $v['type_name_cn'] = $v['type'] == 1 ? "充值成功 " : "投入矿区成功";
            }
        }
        $this->success($list);
    }
}