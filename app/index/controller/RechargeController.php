<?php

namespace app\index\controller;

use api\common\model\LockedModel;
use Eos\Client;
use Eos\Ecc;
use service\Block;
use think\Controller;
use think\Db;

/**
 * Created by PhpStorm.
 * Time: 19:08
 */
class RechargeController extends Controller
{
    public function get_ocx(){
        set_time_limit(0);
        $redis = redis();
        $redis->set("ocx_deposits",0,300);
        $ocx_deposits = $redis->get("ocx_deposits");
        if($ocx_deposits==1){
            return;
        }
        $redis->set("ocx_deposits",1,300);
        $currency_codes = ['usdt','btc','eth','eos'];
        foreach ($currency_codes as $key =>$value){
            $info = Db::name("sun_recharge_log_ocx")->where("currency_code",$value)->order("oxc_id desc")->find();
            $ocx_id = 0;
            if(!empty($info)){
                $ocx_id = $info['oxc_id'];
            }
            $secret_key ='eqGDgRcffdWhmzGBRfnZuAvsQP3es3syCh60jkGF';
            $page = 1;
            while(1){
                $time = time()."000";
                $payload = 'GET|/api/v2/deposits|access_key=L0TAi4hoVC7vWKC2IuitzMQhfH7QhFI4xaZ4Smxx&currency_code='.$value.'&limit=10&page='.$page.'&tonce='.$time;
                $sign = hash_hmac('sha256',$payload,$secret_key);
                $url = 'https://openapi.ocx.app/api/v2/deposits?access_key=L0TAi4hoVC7vWKC2IuitzMQhfH7QhFI4xaZ4Smxx&currency_code='.$value.'&limit=10&page='.$page.'&tonce='.$time.'&signature='.$sign;
                $res = curl_get($url);
                var_dump($res);
                $res_data = \GuzzleHttp\json_decode($res,true);
                $data = [];
                if(!empty($res_data['data'])){
                    $data = $res_data['data'];
                }
                $is_need = true;
                if($data){
                    foreach ($data as $k=>$v){
                        if($v['id']>$ocx_id){
                           $log = [
                               'oxc_id' =>$v['id'],
                               'currency_code'=>$value,
                               'cid'=>$key+1,
                               'done_at'=>$v['done_at'],
                               'txid'=>$v['txid'],
                               'money'=>round($v['amount'],5),
                               'address'=>$v['fund_uid'],
                               'status'=>$v['state'],
                               'create_time'=>time(),
                               'is_use'=>0,
                           ];
                           $log_id =  Db::name("sun_recharge_log_ocx")->insertGetId($log);
                        }else{
                            $is_need = false;
                            break;
                        }
                    }
                }else{
                    $is_need = false;
                }
                if(count($data)<10){
                    break;
                }
                if(!$is_need){
                    break;
                }
                sleep(1);
                $page ++;
            }
            sleep(1);
        }
        $redis->set("ocx_deposits",0,300);
    }
    public function recharge(){
        set_time_limit(0);
        $time = time();
        $time2 = time()+1;
        $str_list = ['','usdt_balance',"btc_balance",'eth_balance','eos_balance'];
        while($time2-$time < 50) {
            $select = Db::name("sun_recharge")->where(['type'=>1,'status'=>0,'is_use'=>0])->where("update_time","<",$time)->page(1,10)->order("update_time asc")->select()->toArray();
            foreach ($select as $k=>$v) {
                Db::name('sun_recharge')->where("id",$v['id'])->update(['is_use'=>1]);
            }
            foreach ($select as $k=>$v) {
                Db::startTrans();
                $info = Db::name("sun_recharge_log_ocx")->where(["address"=>$v['address'],"money"=>$v['money'],'is_use'=>0,"cid"=>$v['cid']])->lock(true)->find();
                if(!empty($info)) {
                    $res = Db::name("sun_recharge_log_ocx")->where("id",$info['id'])->update(['is_use'=>1]);
                    if($res !== false) {
                        Db::name('sun_recharge')->where("id",$v['id'])->update(['status'=>1]);
                        $account = Db::name("sun_account")->where("mid",$v['mid'])->lock(true)->find();
                        $str = $str_list[$v['cid']];
                        $balance = $account[$str]+$v['money'];
                        $account_data = [$str=>$balance];
                        $res = Db::name("sun_account")->where("id",$account['id'])->update($account_data);
                        if($res !== false) {
                            $account_log = [
                                "mid" => $v['mid'],
                                'type'=>1,
                                'cid'=>$v['cid'],
                                'oid'=>$v['id'],
                                'money'=>$v['money'],
                                'create_time'=>time()
                            ];
                            $res = Db::name("sun_account_log")->insertGetId($account_log);
                            if($res) {
                                Db::commit();
                                continue;
                            }
                        }
                    }
                    Db::rollback();
                }else{
                    $c_data = ['is_use'=>0,'update_time'=>time()];
                    if(time() - $v['create_time'] > 86400) {
                        $c_data['status'] = 2;
                    }
                    Db::name('sun_recharge')->where("id",$v['id'])->update($c_data);
                    Db::commit();
                }
            }
            if(count($select)<10) {
                break;
            }
            $time2 = time();
        }
    }
    public function test_ocx(){
        $secret_key ='eqGDgRcffdWhmzGBRfnZuAvsQP3es3syCh60jkGF';
        $time = time()."000";
        $payload = 'GET|/api/v2/deposits|access_key=L0TAi4hoVC7vWKC2IuitzMQhfH7QhFI4xaZ4Smxx&currency_code=btc&limit=10&page=1&tonce='.$time;//&limit=2
        $sign = hash_hmac('sha256',$payload,$secret_key);
        $url = 'https://openapi.ocx.app/api/v2/deposits?access_key=L0TAi4hoVC7vWKC2IuitzMQhfH7QhFI4xaZ4Smxx&currency_code=btc&limit=10&page=1&tonce='.$time.'&signature='.$sign;
        //var_dump($url);
        $data = curl_get($url);
        var_dump($data);
        exit();
    }

}