<?php

namespace app\index\controller;

use cmf\phpqrcode\QRcode;
use service\Block;
use think\Controller;
use think\Db;

/**
 * 红包领取类
 */
class RedpacketController extends Controller
{
    public function  index(){
        $redis = redis();
        $data = input();
        if(empty($data['id'])){
            $this->error("信息错误");
        }
        $id = $data['id'];
        $info = $redis -> get("redpacket_info_".$id);
        if(empty($info)){
            $info = Db::name('red_packet as rp ')->where("rp.sn",$id)->join("members m ","m.id = rp.mid")->field("m.mobile,rp.remark,rp.status,rp.sn as id,rp.num,rp.end_time,rp.id as rid")->cache(60)->find();
            if(empty($info)){
                $this->error("信息错误");
            }else{
                $info['mobile'] =  $info['mobile'] = substr($info['mobile'],0,3)."*****".substr($info['mobile'],-3,3);
            }
            $redis->set("redpacket_info_".$id,json_encode($info),3600);
        }
        $this->assign("info",$info);
        return $this->fetch();
    }

    public function receive(){
        $redis = redis();
        $data = input();
        if(empty($data['mobile']) || !preg_match("/^[0-9][0-9]*$/", $data['mobile'])){
            $this->error("信息错误");
        }
        if(empty($data['id'])){
            $this->error("信息错误");
        }
        $id = $data['id'];
        $info = $redis -> get("redpacket_info_".$id);
        if(empty($info)){
            $info = Db::name('red_packet as rp ')->where("rp.sn",$id)->join("members m ","m.id = rp.mid")->field("m.mobile,rp.remark,rp.status,rp.sn as id,rp.end_time,rp.id as rid,rp.num")->cache(60)->find();
            if(empty($info)){
                $this->error("信息错误");
            }else{
                $info['mobile'] =  $info['mobile'] = substr($info['mobile'],0,3)."*****".substr($info['mobile'],-3,3);
            }
            $redis->set("redpacket_info_".$id,json_encode($info),3600);
        }
        $url = $_SERVER['SERVER_NAME'];
        $url='http://'.$url.'/index/redpacket/receive_log/id/'.$id."/mobile/".$data['mobile'];
        if ($this->request->isPost()){
            if(time()<$info['end_time'] && $info['status']==1) {
                $num = $redis->get("redpacket:" . $id);
                $num_index = $redis->get("redpacket_index:" . $id);
                $num_index = $num_index ? $num_index : 0;
                $mobile_list = $redis->get("redpacket_list:" . $id);
                $mobile_list = $mobile_list ? $mobile_list : [];
                if (($num_index < $num) && !in_array($data['mobile'], $mobile_list)) {
                    $num_index++;
                    $mobile_list[] = $data['mobile'];
                    $redis->set("redpacket_list:".$id,json_encode($mobile_list),86401);
                    $redis->set("redpacket_index:".$id,$num_index,86400);
                    $data_log = Db::name("red_packet_log")->where(['mobile' => $data['mobile'], 'rid' => $info['rid']])->find();
                    if (empty($data_log)) {
                        $rp_logs = Db::name("red_packet_log")->where("rid", $info['rid'])->page($num_index, 1)->select()->toArray();
                        if (!empty($rp_logs[0])) {
                            $rp_log = $rp_logs[0];
                            if (!($rp_log['status'] > 0)) {
                                Db::startTrans();
                                $log_data = ['mobile' => $data['mobile'],'draw_time'=>time(), 'status' => 1,'ip'=>get_client_ip()];
                                $res = Db::name("red_packet_log")->where('status', 0)->where('id', $rp_log['id'])->update($log_data);
                                if ($res !== false) {
                                    $trc = [
                                        'rpl_id' => $rp_log['id'],
                                        'mobile' => $data['mobile'],
                                        'money' => $rp_log['money'],
                                        'status' => 0,
                                        'is_use' => 0,
                                        'create_time' => time(),
                                    ];
                                    $res = Db::name("red_packet_traccout")->insert($trc);
                                    if ($res) {
                                        Db::commit();
                                    } else {
                                        Db::rollback();
                                    }
                                } else {
                                    Db::rollback();
                                }
                            }
                        }
                    }
                }else{
                    if(in_array($data['mobile'], $mobile_list)){
                        $this->error('你已领取过该红包',$url);
                    }
                }
            }else{
                $this->error('该红包已过期',$url);
            }
            $this->success("成功",$url);
        }
    }
    public function receive_log($id,$mobile){
        if(empty($id) || empty($mobile)){
            $this->error("信息错误");
        }
        $redis = redis();
        $info = $redis -> get("redpacket_info_".$id);
        if(empty($info)){
            $info = Db::name('red_packet as rp ')->where("rp.sn",$id)->join("members m ","m.id = rp.mid")->field("m.mobile,rp.remark,rp.status,rp.sn as id,rp.end_time,rp.id as rid,rp.num")->cache(60)->find();
            if(empty($info)){
                $this->error("信息错误");
            }else{
                $info['mobile'] =  $info['mobile'] = substr($info['mobile'],0,3)."*****".substr($info['mobile'],-3,3);
            }
            $redis->set("redpacket_info_".$id,json_encode($info),3600);
        }
        $log = Db::name("red_packet_log")->where("status",1)->where("rid",$info['rid'])->order("draw_time desc")->field("mobile,money,draw_time")->select()->toArray();
        $type = 2;
        if($info['end_time']<time()){
            $type = 3;
        }
        $money = 0;
        $num = 0;
        foreach ($log as $k=>&$v){
            if($v['mobile'] == $mobile){
                $type = 1;
                $money = number_format($v['money'], 2, ".", "");;
            }
            $num++ ;
            $v['mobile'] =  substr($v['mobile'],0,3)."*****".substr($v['mobile'],-3,3);
            $v['draw_time'] = date("Y-m-d H:i:s",$v['draw_time']);
        }
        $this->assign("num",$num);
        $this->assign("info",$info);
        $this->assign("type",$type);
        $this->assign("money",$money);
        $this->assign("list",$log);
        return $this->fetch();
    }
    //跑过期的定时任务;
    public function rp_expires(){
        $time = time();
        $rp_list = Db::name("red_packet")->where(['status'=>1,'is_use'=>0])->where("end_time","lt",$time)->select()->toArray();
        if(empty($rp_list)){
            return;
        }
        foreach ($rp_list as $k =>$v){
            Db::name("red_packet")->where(['is_use'=>0,'id'=>$v['id']])->update(['is_use'=>1]);
        }
        foreach ($rp_list as $k =>$v){
            Db::name("red_packet_log")->where(['status'=>0,'rid'=>$v['id']])->update(['status'=>2]);
            $info = Db::name("members")->where('id',$v['mid'])->find();
            $received_money = Db::name("red_packet_log")->where(['status'=>1,'rid'=>$v['id']])->sum("money");
            $received_num = Db::name("red_packet_log")->where(['status'=>1,'rid'=>$v['id']])->count("id");
            $s_money = round($v['money'] - $received_money,2);
            $s_num = round($v['num'] - $received_num);
            if($s_money>0 && $s_num>0){
                $trc = [
                    'rpl_id' => $v['id'],
                    'mobile' => $info['mobile'],
                    'money' => $s_money,
                    'status' => 0,
                    'is_use' => 0,
                    'create_time' => time(),
                    'type'=>2
                ];
                $res = Db::name("red_packet_traccout")->insert($trc);
            }
            Db::name("red_packet")->where(['status'=>1,"id"=>$v['id']])->update(['status'=>2,'received_money'=>$received_money,'received_num'=>$received_num]);
        }
    }
    //跑转账红包的定时任务
    public function rp_traccout(){
        $i= 1;
        while ($i<10){
            $list = Db::name("red_packet_traccout as rpt")->where(['rpt.status'=>0,'rpt.is_use'=>0])->join("members m","m.mobile=rpt.mobile and m.address !='' ")->field("rpt.id")->page(1,3)->select()->toArray();
            if(!empty($list)){
                foreach ($list as $k=>$v){
                    Db::name("red_packet_traccout")->where(['is_use'=>0,'id'=>$v['id']])->update(['is_use'=>1]);
                }
                $block = new Block();
                foreach ($list as $key=>$val){
                    $return = $block->red_traccout($val['id']);
                    if ($return === false) {
                        Db::name("red_packet_traccout")->where(['id'=>$val['id']])->update(['is_use'=>0]);
                       continue;
                    }
                    if (isset($return['status']) && $return['status'] == 1) {
                        Db::name("red_packet_traccout")->where(['status'=>0,'id'=>$val['id']])->update(['status'=>1]);
                    }else{
                        Db::name("red_packet_traccout")->where(['id'=>$val['id']])->update(['is_use'=>0]);
                    }
                }
            }
            $i++;
            sleep(5);
        }
    }

    function getActivityImg($url='')
    {
        //二维码中间添加logo
        $logo = 'http://testzing.zingdapp.com/static/reg/image/logo1.png';
        $template = 'http://testzing.zingdapp.com/static/reg/image/share_bg.jpg';
        $QR = "base.png";
        $last = "last.png";
        $errorCorrectionLevel = 'H'; //防错等级
        $matrixPointSize = 17; //二维码大小

        //生成二维码
        //参数内容:二维码储存内容，生成存储，防错等级，二维码大小，白边大小
        QRcode::png($url, $QR, $errorCorrectionLevel, $matrixPointSize, 1);
        //合并logo跟二维码-----------------start
        $QR = imagecreatefromstring(file_get_contents($QR));
        $logo = imagecreatefromstring(file_get_contents($logo));
        $QR_width = imagesx($QR);
        $logo_width = imagesx($logo);
        $logo_height = imagesy($logo);
        $logo_qr_width = $QR_width / 5;
        $scale = $logo_width / $logo_qr_width;
        $logo_qr_height = $logo_height / $scale;
        $from_width = ($QR_width - $logo_qr_width) / 2;
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        //imagepng($QR,$last); // 生成带log的二维码图片 存储到last*/
        //合并logo跟二维码-----------------end


        //$QR = imagecreatefromstring(file_get_contents($QR));
        $template1 = imagecreatefromstring(file_get_contents($template));
        $QR_width = imagesx($template1);
        $logo_width = imagesx($QR);
        $logo_height = imagesy($QR);
        imagecopyresampled($template1, $QR, 150, 455, 0, 0, $logo_width, $logo_height, $logo_width, $logo_height);
        imagepng($template1,$last);
        $fileName=md5(basename($template).time().$url);
        $EchoPath=dirname(dirname(dirname(dirname(__FILE__)))).'/public/static/reg/image/'.$fileName.'.png';
        imagepng($template1,$EchoPath);
        imagedestroy($template1);
        //返回生成的路径
        $EchoPath =  '/static/reg/image/'.$fileName.'.png';
        return $EchoPath;
    }
}