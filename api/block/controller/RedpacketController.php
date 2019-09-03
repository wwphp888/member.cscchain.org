<?php
/**
 * Created by PhpStorm.
 * Date: 2019/5/20
 * Time: 10:51
 */

namespace api\block\controller;


use api\common\controller\ApiUserController;
use cmf\phpqrcode\QRcode;
use service\Block;
use think\Db;
use think\Log;

class RedpacketController extends ApiUserController
{
    /**
     * 钱包首页
     */
    public function index()
    {
        $data1 = [
            "zing_num"  => "Maximum input 20000 WSEC",
            "max_a_num" => "Maxinum input 200 WSEC",
            "max_num"   => "Maximum input 100",
            'Remarks'   => "红包24小时有效，剩余部分返回可用余额。"
        ];
        $data = [
            "zing_num"  => "",
            "max_a_num" => "",
            "max_num"   => "",
            'Remarks'   => "红包24小时有效，剩余部分返回可用余额。"];

        $this->success("信息返回成功",$data);
    }

    /**
     *
     */
    public function rp_info($id)
    {
        $data = [
            'id'        => "",
            "url"       => "",
            "title"     => "",
            "reamrk"    => "",
            "money"     => "",
            "num"       => "",
            "type"      => "1"
        ];

        if(!empty($id))
        {
            $red_packet = Db::name("red_packet")
                            ->where("sn",$id)
                            ->find();

            if(!empty($red_packet))
            {
                $members = Db::name("members")
                            ->where("id", $red_packet['mid'])
                            ->find();

                $title     = substr($members['mobile'],0,3)."*****".substr($members['mobile'],-3,3)."的红包";
                $url       =  "http://".$_SERVER['SERVER_NAME'].'/index/redpacket/index/id/'.$id;
                $share_img = "http://".$_SERVER['SERVER_NAME'].'/static/reg/image/rp_share.jpg';

                if(empty($red_packet['red_img']))
                {
                    $image_url = $this->getActivityImg($url);

                    Db::name("red_packet")
                        ->where("sn",$id)
                        ->update([
                            'red_img'=>$image_url
                        ]);

                    $red_packet['red_img'] = $image_url;
                }

                $image_url =  "http://".$_SERVER['SERVER_NAME'].$red_packet['red_img'] ;
                $data      = [
                    'id'            => $id,
                    "url"           => $url,
                    "title"         => $title,
                    "remark"        => $red_packet['remark'],
                    "money"         => ($red_packet['money']*1)."",
                    "num"           => $red_packet['num']."",
                    "type"          => $red_packet['type']."",
                    "share_title"   => '【ZINGDAPP】好友送您ZING大红包',
                    "share_img"     => $share_img,
                    "image_url"     => $image_url
                ];
            }
        }

        $this->success("信息返回成功",$data);
    }

    public function getActivityImg($url='')
    {
        //二维码中间添加logo
        $logo     = 'http://'.$_SERVER['SERVER_NAME'].'/static/reg/image/logo1.png';
        $template = 'http://'.$_SERVER['SERVER_NAME'].'/static/reg/image/share_bg.jpg';
        $QR       = "static/reg/img/base".time().rand(10000,99999).".png";
        $last     = "static/reg/img/last".time().rand(10000,99999).".png";

        $errorCorrectionLevel = 'H'; //防错等级
        $matrixPointSize      = 9; //二维码大小

        //生成二维码
        //参数内容:二维码储存内容，生成存储，防错等级，二维码大小，白边大小
        QRcode::png($url, $QR, $errorCorrectionLevel, $matrixPointSize, 1);

        //合并logo跟二维码-----------------start
        $QR             = imagecreatefromstring(file_get_contents($QR));
        $logo           = imagecreatefromstring(file_get_contents($logo));
        $QR_width       = imagesx($QR);
        $logo_width     = imagesx($logo);
        $logo_height    = imagesy($logo);
        $logo_qr_width  = $QR_width / 5;
        $scale          = $logo_width / $logo_qr_width;
        $logo_qr_height = $logo_height / $scale;
        $from_width     = ($QR_width - $logo_qr_width) / 2;

        imagecopyresampled($QR,
                            $logo,
                            $from_width,
                            $from_width,
                        0,
                        0,
                            $logo_qr_width,
                            $logo_qr_height,
                            $logo_width,
                            $logo_height);

        //imagepng($QR,$last); // 生成带log的二维码图片 存储到last*/
        //合并logo跟二维码-----------------end


        //$QR = imagecreatefromstring(file_get_contents($QR));
        $template1   = imagecreatefromstring(file_get_contents($template));
        $QR_width    = imagesx($template1);
        $logo_width  = imagesx($QR);
        $logo_height = imagesy($QR);

        imagecopyresampled($template1,
                            $QR,
                        150,
                        392,
                        0,
                        0,
                            $logo_width,
                            $logo_height,
                            $logo_width,
                            $logo_height);
        imagejpeg($template1, $last);

        $fileName = md5(basename($template).time().$url);
        $EchoPath = dirname(dirname(dirname(dirname(__FILE__)))).'/public/static/reg/img/'.$fileName.'.jpeg';

        imagejpeg($template1, $EchoPath);
        imagedestroy($template1);

        //返回生成的路径
        $EchoPath = '/static/reg/img/'.$fileName.'.jpeg';

        return $EchoPath;
    }

    /**
     * 收发红包记录
     */
    public function rp_log($rp_type=1,$page=1)
    {
        $list = [];

        if($rp_type == 1)
        {
            $list = Db::name("red_packet")
                        ->where("mid",$this->uid)
                        ->where("status","gt",0)
                        ->field("money,sn as id,create_time")
                        ->order("create_time desc")
                        ->page($page,15)
                        ->select()
                        ->toArray();
        } else {
            $where['id'] = $this->uid;
            $user = dbMember("members")->where($where)->find();
            $list = Db::name("red_packet_log as rpl")
                        ->where("rpl.mobile",$user['mobile'])
                        ->join("red_packet rp ","rp.id = rpl.rid")
                        ->field("rpl.money,rp.sn as id,draw_time as create_time")
                        ->page($page,15)
                        ->order("draw_time desc")
                        ->select()
                        ->toArray();
        }

        $f = $rp_type == 1 ? "-" : "+";

        foreach ($list as $k => &$v) {
            $v['money']       = $f." ".(round($v['money'],2)*1);
            $v['create_time'] = date("Y-m-d H:i:s", $v['create_time']);
        }

        $this->success("信息返回成功",$list);
    }

    public function rp_draw_log($id, $page)
    {
        $list = [];

        $red_info = Db::name("red_packet")
                    ->where('sn',$id)
                    ->field("id")->find();

        if(!empty($red_info))
        {
            $list = Db::name("red_packet_log")
                        ->where("rid",$red_info['id'])
                        ->where("status",1)
                        ->order("draw_time desc")
                        ->field("mobile,money,draw_time")
                        ->page($page,10)
                        ->select()
                        ->toArray();

            foreach ($list as $k => &$v) {
                $v['money']     = (round($v['money'],2)*1)."";
                $v['mobile']    = substr($v['mobile'],0,3)."*****".substr($v['mobile'],-3,3);
                $v['draw_time'] = date("Y-m-d H:i:s", $v['draw_time']);
            }
        }

        $this->success("信息返回成功",$list);
    }

    /**
     *  红包添加
     */
    public function rp_add()
    {
        set_time_limit(0);

        //禁用用户
        $uid         = $this->uid;
        $where['id'] = $uid;
        $user        = dbMember("members")->where($where)->find();

        if ($user['is_dis_award'] == 1)
        {
            return $this->error(lang("account_frozen"));
        }

        $a_money = 0;
        $min     = 0.01;
        $max     = 20000;
        $a_max   = 200;
        $max_num = 100;

        //币种ID ,强制
        session('region_transaction_' . $uid, "end");
        $currency_id = 1;

        if ($this->request->isPost())
        {
            $data           = $this->data;
            $data['remark'] = empty($data['remark'])?"恭喜发财":$data['remark'];

            if ($data['type'] != 1 && $data['type'] != 2)
            {
                return $this->error(lang('error'));
            }
            if (!preg_match("/^[1-9][0-9]*$/", $data['num']) || $data['num'] > $max_num)
            {
                if($data['num'] > $max_num)
                {
                    return $this->error("红包数量不能超过100个");
                }

                return $this->error(lang('red_input_error'));
            }

            $memberPay = $user['trade_pwd'];
            if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $data['money']) || !($data['money'] >= 0) || $data['money'] > $max) {
                session('region_transaction_' . $uid, "end");
                return $this->error(lang('input_error'));
            }
            if ($data['type'] == 1)
            {
                if ($data['money'] > $a_max)
                {
                    return $this->error("单个红包不能超过200");
                }

                $a_money       = $data['money'];
                $data['money'] = $data['money'] * $data['num'];
            } else {
                if (($min * $data['num']) > $data['money'])
                {
                    session('region_transaction_' . $uid, "end");

                    return $this->error(lang('input_error'));
                }
                if(($data['money']/$data['num']) > $a_max)
                {
                    session('region_transaction_' . $uid, "end");

                    return $this->error("单个红包不能超过200");
                }
            }

            $payPwd = cmf_password($data['payPassword']);
            //判断交易密码
            if ($payPwd != $memberPay)
            {
                session('region_transaction_' . $uid, "end");

                return $this->error(lang("transaction_password_error"));
            }

            //判断用户是否还在交易
            $traccount_start = session('region_transaction_' . $uid);

            if ($traccount_start == 'run')
            {
                return $this->error(lang("lease_wait_patiently"));
            }

            session('region_transaction_' . $uid, "run");
            $block      = new Block();
            $have_money = $block->getUserZing($uid);//获取链上zing

            //$have_money = 200000;
            //如果转入金额大于可用
            if ($data['money'] > $have_money)
            {
                session('region_transaction_' . $uid, "end");

                return $this->error(lang('insufficient_of'));
            }
            //判断格式,并且不大于0
            if (!preg_match('/^[0-9]+(.[0-9]{1,8})?$/', $data['money']) || !($data['money'] >= 0)) {
                session('region_transaction_' . $uid, "end");

                return $this->error(lang('input_error'));
            }

            $money   = $data['money'];
            $ordersn = makeOrderNo("RP");

            $data = array(
                "sn"            => $ordersn,
                'mid'           => $user['id'],
                'money'         => $money,
                'type'          => $data['type'],
                'num'           => $data['num'],
                'remark'        => $data['remark'],
                'status'        => 0,
                'create_time'   => time(),
                'end_time'      => time() + 86400,
                'currency_id'   => $currency_id
            );

            $res    = Db::name("red_packet")->insertGetId($data);
            $return = $block->red_transaction($res);
            session('region_transaction_' . $uid, "end");

            //$return = ['status'=>1];
            //如果是false 直接失败
            if ($return === false)
            {
                return $this->error(lang('red_error'));
            }
            if (isset($return['status']) && $return['status'] == 1)
            {
                //更新转账记录
                Db::name("red_packet")
                    ->where(array("id" => $res))
                    ->update([
                        "status" => 1,
                        "return_msg" => json_encode($return)
                    ]);

                //添加投注记录
                Db::startTrans();

                if ($data['type'] == 1)
                {
                    for ($i = 1; $i <= $data['num']; $i++) {
                        $alldata[] = [
                            'money'         => $a_money,
                            'rid'           => $res,
                            'mid'           => 0,
                            'status'        => 0,
                            'create_time'   => time(),
                            'draw_time'     => 0,
                        ];
                    }
                } else {
                    $num_array = $this->getBonus($data['money'], $data['num']);

                    foreach ($num_array as $k => $v){
                        $alldata[] = [
                            'money'         => $v,
                            'rid'           => $res,
                            'mid'           => 0,
                            'status'        => 0,
                            'create_time'   => time(),
                            'draw_time'     => 0,
                        ];
                    }
                }

                if (empty($alldata))
                {
                    return '';
                }
                if (Db::name("red_packet_log")->insertAll($alldata))
                {
                    Db::commit();

                    $redis = redis();
                    $redis->set("redpacket:".$ordersn, $data['num'],86400);

                    return $this->success("红包创建成功！",['id'=>$ordersn]);
                } else {
                    Log::error("插入数据失败红包id:" . $res);
                    Db::rollback();
                }
            } else {
                Db::name("red_packet")
                    ->where("id", $res)
                    ->update([
                        "status" =>-1,
                        "return_msg" => $return
                    ]);

                return $this->error(lang('red_error'));
            }
        }
    }

    public function sqr($n)
    {
        return $n*$n;
    }

    public function xRandom($bonus_min, $bonus_max)
    {
        $sqr      = intval($this->sqr($bonus_max-$bonus_min));
        $rand_num = rand(0, ($sqr-1));

        return intval(sqrt($rand_num));
    }

    //用分为单位
    public  function getBonus($bonus_total, $bonus_count)
    {
        $a_max       = 20000;
        $bonus_total = $bonus_total * 100;
        $bonus_min   = 1;
        $result      = [];
        $average     = $bonus_total / $bonus_count;
        $bonus_max   = ($bonus_total / $bonus_count)*3;
        $bonus_max   = ($bonus_max > $a_max)?$a_max:$bonus_max;

        for ($i = 0; $i < $bonus_count; $i++)
        {
            if (rand($bonus_min, $bonus_max) > $average)
            {
                // 在平均线上减钱 
                $temp         = $bonus_min + $this->xRandom($bonus_min, $average);
                $result[$i]   = $temp;

                $bonus_total -= $temp;
            } else {
                // 在平均线上加钱 
                $temp         = $bonus_max - $this->xRandom($average, $bonus_max);
                $result[$i]   = $temp;
                $bonus_total -= $temp;
            }
        }

        // 如果还有余钱，则尝试加到小红包里，如果加不进去，则尝试下一个。 
        while ($bonus_total > 0) {
            for ($i = 0; $i < $bonus_count; $i++) {
                if ($bonus_total > 0 && $result[$i] < $bonus_max)
                {
                    $result[$i]++;
                    $bonus_total--;
                }
            }
        }

        // 如果钱是负数了，还得从已生成的小红包中抽取回来 
        while ($bonus_total < 0) {
            for ($i = 0; $i < $bonus_count; $i++) {
                if ($bonus_total < 0 && $result[$i] > $bonus_min)
                {
                    $result[$i]--;
                    $bonus_total++;
                }
            }
        }

        $cc = 0;
        foreach ($result as $k=>$v){
            $result[$k] = round($v/100,2);
            $cc        += $result[$k];
        }

        return $result;
   }
}