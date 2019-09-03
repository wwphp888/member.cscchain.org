<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\admin\model;

use think\Model;
use service\Block;
use think\Db;

class DiggingsModel extends Model
{
    //传入时间精确到分钟date('Y-m-d H:i:00')
    //获取开奖号
    public function getBtcUsd($time)
    {
        //Windows运行环境返回固定值
        if (php_uname('s') == 'Windows NT') {
            return 8;
        }
        $time = strtotime($time);
        $start = $time - 180;
        $end = $time + 180;
        $url = 'https://api-pub.bitfinex.com/v2/candles/trade:1m:tBTCUSD/hist?limit=30';
        if ($start) {
            $url .= '&start=' . $start . '000';
            if ($end) {
                $url .= '&end=' . $end . '000';
            }
        }
        $url .= '&sort=-1';
        $time = $time * 1000;
        $curl = curl_init();//初始化curl模块
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);//是否显示头信息
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);//是否自动显示返回的信息
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
        // curl_setopt($curl, CURLOPT_POST, 1);//post方式提交
        // curl_setopt($curl, CURLOPT_POSTFIELDS,http_build_query(['addr'=>'16BfduKNLVVyA6tADE64vusSp3XxVRR8qN','page'=>0]));//要提交的信息
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在
        $data = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($data, true);
        if (empty($data)) {
            return false;
        }
        foreach ($data as $value) {
            if ($value[0] == $time) {
                if ($value[1]) {
                    //根据获奖价格转换为开奖号
                    $number = intval($value[1]);
                    $arr = str_split($number);
                    $arrcont = 0;
                    foreach ($arr as $key => $value) {
                        $arrcont += $value;  
                    }
                    return [
                        'lottery_num' => $arrcont % 10,
                        'btc_usdt' => $number,
                    ];
                }
                return false;
            }
        }
        return false;
    }

    //开奖并创建下次期号
    public function runLottery()
    {
        $time = time();
        $where = [
            'period_no' => date('Ymd', $time - 86400),
            'status' => 2
        ];
        $resut = Db::name('period')->where($where)->find();
        if ($resut) {
            //设置开奖时间是
            $runtime = date('Y-m-d 17:00:00');
            $rtime = strtotime($runtime);
            if ($time < $rtime) return;
            $lottery_num = $this->getBtcUsd($runtime);
            //$lottery_num = [ 'lottery_num' => 4, 'btc_usdt' => 8888,];
            if ($lottery_num == false) {
                return;
            }
            $regioninfo = Db::name('region')->where('hit', 'like', '%' . $lottery_num['lottery_num'] . '%')->find();
            $data = [
                'advanced' => $regioninfo['id'],
                'ordinary' => $regioninfo['ordinary'],
                'base' => $regioninfo['base'],
                'update_time' => $time,
                'lottery_num' => $lottery_num['lottery_num'],// 开奖数字
                'lottery_time' => $rtime,
                'btc_usdt' => $lottery_num['btc_usdt'],
                'status' => 1
            ];
            Db::name('period')->where(['id' => $resut['id']])->update($data);
            $this->transfer($resut['id']);
        }
        $period_no = date('Ymd');
        $isin = Db::name('period')->where(['period_no' => $period_no])->find();
        if (empty($isin)) {
            //设置锁盘时间
            $end_time = strtotime(date('Y-m-d 16:00:00', $time + 86400));
            $data = [
                'period_no' => date('Ymd'),
                'status' => 0,
                'create_time' => $time,
                'end_time' => $end_time
            ];
            Db::name('period')->insert($data);
        }
        $where = [
            'period_no' => date('Ymd', $time - 86400),
            'status' => 1
        ];
        $resut = Db::name('period')->where($where)->find();
        if (!empty($resut)) {
            $this->changeLotteryStatus($resut['id']);
        }

    }

    //改变用户中奖状态
    public function changeLotteryStatus($period_id)
    {
        $periodinfo = Db::name('period')->where(['id' => $period_id])->find();
       // $ordinary = "'".$periodinfo['ordinary']."'";
      //  $base = "'".$periodinfo['base']."'";
        Db::name('betting_log')->where(['period_no' => $periodinfo['period_no'], 'region_id' => $periodinfo['advanced']])->update(['status' => 1]);
        Db::name('betting_log')->where(['period_no' => $periodinfo['period_no']])->where("region_id",'in',$periodinfo['ordinary'])->update(['status' => 2]);
        Db::name('betting_log')->where(['period_no' => $periodinfo['period_no']])->where("region_id",'in',$periodinfo['base'])->update(['status' => 3]);

    }

    //转移五大区的币到总账户
    public function transfer($period_id)
    {
        if (empty($period_id)) {
            return false;
        }
        $address = 'aoohqh2pvyqu'; //总账户
        $block = new block();
        $region = Db::name('region')->where(1)->select();
        foreach ($region as $key => $value) {
            $membersinfo = dbMember("members")->where(['address' => $value['address']])->find();
            $balance = $block->getUserZing($membersinfo['id']);
            if ($balance > 0) {
                for ($i = 0; $i < 3; $i++) {
                    $result = $this->interiorTransfer($membersinfo['id'], $balance, $address, 'HT1111');
                    if ($result) {
                        $data = [];
                        $abb = strtolower($value['abb']);
                        $data[$abb] = $balance;
                        Db::name('period')->where(['id' => $period_id])->update($data);
                        break;
                    }
                }

            }
        }
    }

    //后台内部转账
    public function interiorTransfer($mid, $money, $address, $remark = 'HT', $period_no = '')
    {
        $block = new block();
        $ordersn = makeOrderNo("HT");
        $data = array(
            "ordersn" => $ordersn,
            'mid' => $mid,
            'money' => $money,
            'true_money' => $money,
            'poundage' => 0,
            'address' => $address,
            'remark' => $remark,
            'create_time' => time(),
            'update_time' => time(),
            'status' => 0,
            'is_handle' => 1,
            'is_app' => 1,
            'currency_id' => 1,
            "transaction_id" => "--",
            'period_no' => $period_no,
            'status_type' => 3
        );
        $res = Db::name("sun_traccount")->insertGetId($data);
        if ($res) {
            $return = $block->transaction($res);
            //如果是false 直接失败
            if ($return === false) {
                return false;
            }
            if (isset($return['status']) && $return['status'] == 1) {
                Db::name("sun_traccount")->where(array("id" => $res))->update(array("status" => 1, "transaction_id" => $return['transaction_id'], "block_num" => $return["block_num"], "return_msg" => json_encode($return)));
                return true;
            } else {
                Db::name("sun_traccount")->where(array("id" => $res))->update(array("status" => 2, "is_app" => 0, "return_msg" => $return));
                return false;
            }
        } else {
            return false;
        }
    }
}