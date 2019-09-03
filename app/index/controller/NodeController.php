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
class NodeController extends Controller
{
    public function index(){
        $reids = redis();
        $data = input();
        $uid = '';
        $token ='';
        if(!empty($data['token'])) {
            $token = $data['token'];
            $uid = $reids->get("members:" . $data['token']);
        }
        $type = 1;
        if($uid){
            $node = Db::name("node")->where(["mid"=>$uid])->find();
            if(!empty($node)){
                if($node['status']==1){
                    $list = Db::name("node_log")->where(['mid'=>$uid])->select()->toArray();
                    $total_profit  = Db::name("node_log")->where(['mid'=>$uid])->sum('money');
                    $time = strtotime(date("Y-m-d"));
                    $today_profit =  Db::name("node_log")->where(['mid'=>$uid])->where("create_time",">",$time)->sum('money');
                    foreach ($list as $k => &$v){
                        $v['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
                        $v['money'] = round($v['money'],4)."";
                    }
                    $this->assign("total_profit",$total_profit);
                    $this->assign("today_profit",$today_profit);
                    $this->assign("list",$list);
                    return $this->fetch("node_log");
                }
                if($node['status'] < 1){
                    $type = 2;
                }
            }
        }
        $this->assign("token",$token);
        $this->assign("type",$type);
        return $this->fetch();
    }

    public function add(){
        $redis = redis();
        $data = input();
        $uid = '';
        if(!empty($data['token'])) {
            $uid = $redis->get("members:" . $data['token']);
        }
        $has_num = Db::name("node")->where("status",1)->count();
        if($has_num>=50){
            $this->error("申请失败，节点名额已满");
        }
        $node = [];
        $is_can = 0;
        if($uid){
            $node = Db::name("node")->where("mid",$uid)->find();
        }
        if(!empty($node)){
            $is_can = $node['status']==2?1:0;
        }
        if($uid){
            $cc = $redis->get("node:" . $uid);
            if($cc){
                $this->error("申请失败，您还未满足申请条件");
            }
            if(empty($node) || $is_can == 1){
                $moneys = Db::name("betting_log")->where("mid",$uid)->sum("money");
                if($moneys<20000){
                    $redis->set("node:" . $uid, 1,60);
                    $this->error("申请失败，您还不是高级矿工");
                }
                $block = new Block();
                $zing = $block->getUserZing($uid);
                if($zing<100000){
                    $redis->set("node:" . $uid, 1,60);
                    $this->error("申请失败，您还未满足申请条件");
                }
                $child_array = Db::name("members")->where("pid",$uid)->field("id")->select()->toArray();
                if(count($child_array)>3){
                    $type = 0;
                    foreach ($child_array as $key=>$value){
                        $members = dbMember('members')->whereOr(['pid'=>$value['id'],'relation'=>['like','%-'.$value['id'].'-%']])->field('id')->select();
                        $membersall[] = $value['id'];
                        foreach($members as $k => $val) {
                            $membersall[] = $val['id'];
                        }
                        $moneyall = Db::name('betting_log')->where('mid','IN',$membersall)->SUM('money');
                        if($moneyall>=200000){
                            $type++;
                        }
                        if($type==3){
                            break;
                        }
                    }
                    if($type < 3){
                        $this->error("申请失败，您还未满足申请条件");
                    }else{
                        $data = [
                            'type'=>1,
                            'status'=>0,
                            'update_time'=>time(),
                        ];
                        if(!empty($node)){
                            $res  = Db::name("node")->where("id",$node['id'])->update($data);
                        }else{
                            $data['mid'] = $uid;
                            $data['create_time'] = time();
                            $res = Db::name("node")->insertGetId($data);
                        }
                        if($res !== false){
                            $this->success("申请成功，请等待审核");
                        }else{
                            $this->success("申请失败");
                        }
                    }
                }else{
                    $redis->set("node:" . $uid, 1,60);
                    $this->error("申请失败，您还未满足申请条件");
                }
            }else{
                $this->error("您的申请正在审核中，请勿重复申请");
            }
        }else{
            $this->error("请先登录");
        }
    }

    /**
     * 提交审核
     */
    public function every_apply(){
        set_time_limit(0);
        $block = new Block();
        $list = Db::name("node")->where("status",1)->select()->toArray();
        //查看是否降级 降1级
        foreach ($list as $k => $v){
            $zing = $block->getUserZing($v['mid']);
            if($zing<100000){
                $res = Db::name("node")->where("id",$v['id'])->update(['status'=>2]);
                continue;
            }
            $child_array = Db::name("members")->where("pid",$v['mid'])->field("id")->select()->toArray();
            $type = 0;
            foreach ($child_array as $key=>$value){
                $members = dbMember('members')->whereOr(['pid'=>$value['id'],'relation'=>['like','%-'.$value['id'].'-%']])->field('id')->select();
                $membersall[] = $value['id'];
                foreach($members as $k => $val) {
                    $membersall[] = $val['id'];
                }
                $moneyall = Db::name('betting_log')->where('mid','IN',$membersall)->SUM('money');
                if($moneyall>=200000){
                    $type++;
                }
                if($type==3){
                    break;
                }
            }
            if($type<3){
                $res = Db::name("node")->where("id",$v['id'])->update(['status'=>2]);
                continue;
            }
        }
        //获取需要申请的 当前最多50个
        $has_list = Db::name("node")->where("status",1)->select()->toArray();
        $has_num = count($has_list);
        $n_time = time() - 259200;
        $apply_list = Db::name("node")->where("status",0)->where("create_time","<",$n_time)->order("update_time asc")->select()->toArray();
        foreach ($apply_list as $k=>$v){
            if($has_num<50){
                $zing = $block->getUserZing($v['mid']);
                if($zing<100000){
                    $res = Db::name("node")->where("id",$v['id'])->update(['status'=>2]);
                    continue;
                }
                $child_array = Db::name("members")->where("pid",$v['mid'])->field("id")->select()->toArray();
                $type = 0;
                foreach ($child_array as $key=>$value){
                    $members = dbMember('members')->whereOr(['pid'=>$value['id'],'relation'=>['like','%-'.$value['id'].'-%']])->field('id')->select();
                    $membersall[] = $value['id'];
                    foreach($members as $k => $val) {
                        $membersall[] = $val['id'];
                    }
                    $moneyall = Db::name('betting_log')->where('mid','IN',$membersall)->SUM('money');
                    if($moneyall>=200000){
                        $type++;
                    }
                    if($type==3){
                        break;
                    }
                }
                if($type<3){
                    $res = Db::name("node")->where("id",$v['id'])->update(['status'=>2]);
                    continue;
                }else{
                    $res = Db::name("node")->where("id",$v['id'])->update(['status'=>1,'type'=>1]);
                    $has_num ++;
                }
            }else{
                Db::name("node")->where("id",$v['id'])->update(['status'=>2]);
                continue;
            }
        }

        //判断升超级节点
        $list = Db::name("node")->where(["status"=>1])->select()->toArray();
        foreach ($list as $k=>$v){
            $zing = $block->getUserZing($v['mid']);
            if($zing<200000){
                $res = Db::name("node")->where("id",$v['id'])->update(['type'=>1,'update_time'=>time()]);
                continue;
            }
            $child_array = Db::name("members")->where("pid",$v['mid'])->field("id")->select()->toArray();
            $h_num = 0;
            foreach ($child_array as $key=>$value){
                $members = dbMember('members')->whereOr(['pid'=>$value['id'],'relation'=>['like','%-'.$value['id'].'-%']])->field('id')->select();
                $membersall[] = $value['id'];
                foreach($members as $k => $val) {
                    $membersall[] = $val['id'];
                }
                $has_node = Db::name('node')->where('mid','IN',$membersall)->find();
                if(!empty($has_node)){
                    $h_num++;
                }
                if($h_num==3){
                    break;
                }
            }
            if($h_num<3){
                $res = Db::name("node")->where("id",$v['id'])->update(['type'=>1,'update_time'=>time()]);
                continue;
            }else{
                $res = Db::name("node")->where("id",$v['id'])->update(['type'=>2,'update_time'=>time()]);
            }
        }
        return "success";
    }

    public function node_profit(){
        set_time_limit(0);
        $time1 = time();
        $time2 = time()+1;
        while(($time2 - $time1) < 55){
            $need_log = Db::name("betting_log")->where(['is_node'=>0,'is_use'=>0])->find();
            if(!empty($need_log)){
                $res = Db::name("betting_log")->where(['id'=>$need_log['id'],"is_use"=>0])->update(['is_use'=>1]);
                if($res !== false){
                    $relation = getUserInfo($need_log['mid'], 'relation');
                    $status = true;
                    Db::startTrans();
                    if(!empty($relation)){
                        $sun_arr = explode("-", $relation);
                        $type = 0;
                        foreach ($sun_arr as $k=>$v){
                            $node = Db::name("node")->where('mid',$v)->find();
                            if(!empty($node)){
                                if($type<$node['type']){
                                    $now_type = $type;
                                    $type = $node['type'];
                                    if($now_type>0){
                                        $ratio = 0.004;
                                    }else{
                                        $ratio = $type ==2?0.012:0.008;
                                    }
                                    $money = round($need_log['money']*$ratio,4);
                                    if($money>0){
                                        $data = [
                                            'mid' => $v,
                                            'bid' =>$need_log['id'],
                                            'money'=>$money,
                                            'create_time'=>time(),
                                            'is_use'=>1
                                        ];
                                        $res = Db::name("node_log")->insertGetId($data);
                                        if($res){
                                            $mobile = getUserInfo($v, 'mobile');
                                            $traccount = [
                                                'rpl_id'=>$res,
                                                'mobile'=>$mobile,
                                                'money'=>$data['money'],
                                                'status'=>0,
                                                'is_use'=>0,
                                                'create_time'=>time(),
                                                'update_time'=>time(),
                                                'type'=>3
                                            ];
                                            $traccount_id = Db::name("red_packet_traccout")->insertGetId($traccount);
                                            if(!$traccount_id){
                                                $status = false;
                                                break;
                                            }
                                        }else{
                                            $status = false;
                                            break;
                                        }
                                    }
                                }
                            }
                            if($type==2){
                                break;
                            }
                        }
                    }
                    if($status){
                        Db::commit();
                        $res = Db::name("betting_log")->where(['id'=>$need_log['id'],"is_node"=>0])->update(['is_node'=>1]);
                    }else{
                        Db::rollback();
                        $res = Db::name("betting_log")->where(['id'=>$need_log['id'],"is_use"=>1])->update(['is_use'=>0]);
                    }
                }
            }else{
                sleep(10);
            }
            $time2 = time();
        }
    }
}