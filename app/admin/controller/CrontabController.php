<?php

namespace app\admin\controller;

use api\common\model\MedalLogModel;
use app\common\controller\BaseController;

use app\admin\model\DiggingsModel;
use think\Db;
use think\facade\Log;

class CrontabController extends BaseController
{
    //开奖
    //url： http://testzing.zingdapp.com/admin/Crontab/runLottery/pwd/65sd5dskfnA
    public function runLottery()
    {
        set_time_limit(0);
        $time = time();
        $pwd = $this->request->param('pwd');
        if ($pwd != '65sd5dskfnA') return '错误';
        $DiggingsModel = new DiggingsModel();
        $DiggingsModel->runLottery();
    }

    public function  testtime(){
        echo  $runtime = date('Y-m-d 17:00:00');die();
    }
    //节点分红
    public function nodeDivide(){
        $mratio = 1.6; //节点分红利率%
        $bratio = 0.4; //节点补偿分红利率%

        $period_no = date('Ymd',time()-86400);
        $result = Db::name('period')->where(['period_no'=>$period_no])->value('id');
        if(empty($result)){
            exit('错误');
        }
        //计算团队流水
        $nodes = Db::query("SELECT n.mid FROM jm_node n WHERE n.status=1 AND n.mid NOT IN (SELECT nb.mid FROM jm_node_bonus nb WHERE nb.period_no=$period_no)");
        // $nodes = Db::name('node')->where(['status'=>1])->select();
        foreach($nodes as $key => $value) {
            $members = dbMember('members')->whereOr(['pid'=>$value['mid'],'relation'=>['like','%-'.$value['mid'].'-%']])->field('id')->select();
            $membersall[] = $value['mid'];

            foreach($members as $k => $val) {
                $membersall[] = $val['id'];     
            }   
            $moneyall = Db::name('betting_log')->where('mid','IN',$membersall)->SUM('money');
            $data = [
                'mid'=>$value['mid'],
                'create_time'=>time(),
                'streamflow'=>$moneyall,
                'status'=>0,
                'period_no'=>$period_no
            ];
            Db::name('node_bonus')->insert($data);
        }
        //计算分出业绩
        $node_bonus = Db::name('node_bonus')->alias('nb')->join('__NODE__ n','nb.mid=n.mid')->where(['nb.period_no'=>$period_no,'nb.status'=>0])->field('nb.*,n.relation')->select();
        foreach ($node_bonus as $key => $value) {
            $disengagement = '';
            if(!empty($value['relation'])){
                $members = explode('-',$value['relation']);
                foreach ($members as $k => $val) {
                    $node_bonus_info = Db::name('node_bonus')->where(['period_no'=>$period_no,'mid'=>$val])->find();
                    if($node_bonus_info){
                        $disengagement .= $value['disengagement']?$node_bonus_info['id']:','.$node_bonus_info['id'];
                        break;    
                    }   
                }
                
            }
            
            Db::name('node_bonus')->where(['id'=>$value['id']])->update(['status'=>1,'disengagement'=>$disengagement]);
        }

        //计算分红
        $node_bonus = Db::name('node_bonus')->where(['period_no'=>$period_no,'status'=>1])->select();
        foreach ($node_bonus as $key => $value) {
            if(empty($value['disengagement'])){
                $money = round($value['streamflow']*$mratio/100,2);
                $fcstreamflow = 0;
            }else{
                $nbids = explode(',',$value['disengagement']);
                $fcstreamflow = DB::name('node_bonus')->where(['id'=>['in'=>$nbids]])->sum('streamflow');
                $money = round(($value['streamflow']-$fcstreamflow)*$mratio/100+$fcstreamflow*$bratio/100,2);
            }
            $data = [
                'money'=>$money,
                'status'=>2,
                'fcstreamflow'=>$fcstreamflow
            ];
            Db::name('node_bonus')->where(['id'=>$value['id']])->update($data);
        }        
    }

    /**封盘
     * @return string
     */
    public function sealedDisk()
    {
        $time = strtotime(date('Y-m-d 16:00:00'));
        if ($time > time()) {
            return '没有到封盘时间';
        }
        $time = strtotime(date('Y-m-d 17:00:00'));
        if ($time < time()) {
            return '不可封盘';
        }
        $data = [
            'status' => 2,
            'update_time' => time()
        ];

        if (Db::name("period")->where("status", 0)->update($data) === false) {
            return 'sealedDisk错误';
        }
    }

    /**勋章自动使用
     * @return string
     */
    public function useMedal()
    {
        $time = time();
        $data = Db::name("medal")->where("medal", "gt", 0)->where('medal_time', 'lt', $time)->select();
        if (empty($data)) {
            return '';
        }
        foreach ($data as $vo) {
            $update = [
                'medal' => $vo['medal'] - 1,
                'medal_time' => $time + 2592000,
            ];
            $res = Db::name("medal")->where("mid", $vo['mid'])->update($update);
            if ($res) {
                $model = new MedalLogModel();
                $msg = "使用勋章";
                if ($model->log($vo['mid'], 0, 1, 1, $msg) === false) {
                    return $msg . '错误' . $vo['mid'];
                }
            }
        }
    }

    /**
     * 奖励转账
     */
    public function rewardTransfer()
    {
        //查出今天要转的金额

    }
    public function  test(){

        $DiggingsModel = new DiggingsModel();
        // $DiggingsModel->changeLotteryStatus(8);
        var_dump($DiggingsModel->interiorTransfer(42342,100,'mjaegfmpxk4u'));
    }
    //推广奖励
    public function promotionReward(){
        set_time_limit(0);
        $time = time();
        //获取上一期的数据
        $data = Db::name("period")->where("status", 1)->order("id desc")->find();
        //A.计算基础矿区总和
        //A.1没有上一期，直接回退、也就是第一次
        if (empty($data)) {
            return '没有上一期';
        }
        //期数
        $period_no = $data['period_no'];
        $time = time();
        //$locked = Db::name("locked as l")->join("medal m", 'l.mid=m.mid')->where("m.medal_time", 'gt', $time)->where("period_no", $period_no)->where("type", "in", '1,2')->select()->toArray();
        $locked = Db::name("locked")->where("period_no", $period_no)->where("type", "in", '1,2')->where("is_pr",0)->select()->toArray();
        foreach ($locked as $lo) {
            $where = ['id'=>$lo['id']];
            Db::name("locked")->where($where)->update(['is_pr'=>1]);
            $relation = getUserInfo($lo["mid"], 'relation');
            if (empty($relation)) {
                continue;
            }

            $sun_arr = explode("-", $relation);
            //取八层用户

            $sun_arr = array_slice($sun_arr,0,8);

            $medal_arr = Db::name("medal")->where("medal_time", 'gt', $time)->where("mid",'IN',$sun_arr)->field("mid")->select();
            if (empty($medal_arr)) {
                continue;
            }
            $medal_mid = [];
            foreach ($medal_arr as $vo) {
                $medal_mid[] = $vo['mid'];
            }
            foreach ($sun_arr as $key => $sun) {
                if(in_array($sun,$medal_mid)){
                    switch ($key) {
                        case 0:
                            $rate = 50;
                            break;
                        case 1:
                            $rate = 15;
                            break;
                        case 2:
                            $rate = 10;
                            break;
                        default:
                            $rate = 5;
                            break;
                    }
                    $is_in = Db::name('promotion_rewards')->where(['mid'=>$lo["mid"],'locked_id'=>$lo['id']])->value('id');
                    if(empty($is_in)){
                        $market = [
                            'mid' => $sun,
                            'locked_id'=>$lo['id'],
                            'money' => round($lo["income"] * $rate/100,4),
                            'ratio' => $rate,
                            'create_time'=>$time,
                            'status'=>0
                        ];
                        Db::name('promotion_rewards')->insert($market);
                    }    
                }
            }
        }
    }

    /**
     * 跑中奖机制
     */
    public function reward()
    {
        set_time_limit(0);
        //获取上一期的数据
        $data = Db::name("period")->where("status", 1)->order("id desc")->find();
        //A.计算基础矿区总和
        //A.1没有上一期，直接回退、也就是第一次
        if (empty($data)) {
            return '没有上一期';
        }
        //期数
        $period_no = $data['period_no'];

        //高级区
        $advanced = $data['advanced'];
        //普通矿区ID
        $ordinary = $data['ordinary'];
        //基本矿区
        $base = $data['base'];
        if (empty($advanced) || empty($base) || empty($ordinary)) {
            return '矿区异常';
        }
        //计算基础矿区总额
        $baseMoney = Db::name("betting_log")->where("period_no", $period_no)->where("status", 3)->sum("money");

        //分给高级矿区20%
        //$advanced_ratio = $this->config['advanced_ratio']??20;
        $advanced_ratio = 10;
        $advancedRreward = ($baseMoney * $advanced_ratio) / 100;
        //普通矿区分10%
        //$ordinary_ratio = $this->config['ordinary_ratio']??10;
        $ordinary_ratio = 5;
        $ordinaryRreward = ($baseMoney * $ordinary_ratio) / 100;
        //销毁量
        //$destroy_ratio = $this->config['destroy_ratio']??15;
        $destroy_ratio = 20;
        $destroy = ($baseMoney * $destroy_ratio) / 100;
        //储备百分比
        //$reserve_ratio = $this->config['reserve_ratio']??15;
        //$reserve = ($baseMoney * $reserve_ratio) / 100;
        //更新今日销毁
        Db::name("period")->where("id", $data['id'])->update(['destroy' => $destroy]);
        //
        //五区收款总账户
        $dgather_uid = env("account.gather");
        //销毁账户
        $destroy_uid = env("account.destroy");
        //储备资金池
        $reserve_uid = env("account.reserve");
        //销毁账户地址
        $destroy_address = getUserInfo($destroy_uid, "address");
        //储备账户地址
        //$reserve_address = getUserInfo($reserve_uid, "address");
        $this->setAdvanced($period_no, $advancedRreward);
        $this->setOrdinary($period_no, $ordinaryRreward);
        $this->setBase($period_no, $ordinaryRreward);
        $DiggingsModel = new DiggingsModel();
        $res_destroy = Db::name("sun_traccount")->where("period_no", $period_no)->where("mid", $destroy_uid)->where("ordersn", "like", '%XH%')->field("id")->find();
        if (empty($res_destroy)) {
            //销毁账户进入
            if ($DiggingsModel->interiorTransfer($dgather_uid, $destroy, $destroy_address, "XH", $period_no) === false) {
                $DiggingsModel->interiorTransfer($dgather_uid, $destroy, $destroy_address, "XH", $period_no);
            }
        }
       /* $res_reserve = Db::name("sun_traccount")->where("period_no", $period_no)->where("mid", $reserve_uid)->where("ordersn", "like", '%CB%')->field("id")->find();
        if (empty($res_reserve)) {
            //储备账户进入
            if ($DiggingsModel->interiorTransfer($dgather_uid, $reserve, $reserve_address, "CB", $period_no) === false) {
                $DiggingsModel->interiorTransfer($dgather_uid, $reserve, $reserve_address, "CB", $period_no);
            }
        }*/

    }

    /** 插入高级矿区
     * @param $period_no
     * @param $advancedRreward
     */
    private function setAdvanced($period_no, $advancedRreward)
    {
        //计算高级矿区总和
        $advancedMoney = Db::name("betting_log")->where("period_no", $period_no)->where("status", 1)->sum("money");

        //查询所有高级矿区的用户
        $data = Db::name("betting_log")->where("period_no", $period_no)->group("mid")->where("status", 1)->field("sum(money) as totalmoney,mid")->select();
        if (empty($data)) {
            return '';
        }
        $alldata = [];
        foreach ($data as $vo) {
            if (Db::name("locked")->where("mid", $vo['mid'])->where("period_no", $period_no)->where("type", 1)->find()) {
                continue;
            }
            $totalmoney = $vo['totalmoney'];
            // 计算用户收益
            $income = (($totalmoney / $advancedMoney) * $advancedRreward);
            // 计算用户总金额
            $money = $income + $totalmoney;
            $pid = getUserInfo($vo['mid'], 'pid');
            $alldata[] = [
                'money' => $money,
                'period_no' => $period_no,
                'mid' => $vo['mid'],
                'type' => 1,
                'create_time' => time(),
                'income' => $income,
                'pid' => $pid,
                'unlock_time' => (strtotime(date("Y-m-d")) + 86400 * 5) + 1,
            ];
        }
        if (empty($alldata)) {
            return '';
        }
        Db::startTrans();
        if (Db::name("locked")->insertAll($alldata)) {
            Db::commit();
        } else {
            Log::error("插入高级矿区数据失败{$period_no}");
            Db::rollback();
        }
    }

    /** 插入普通矿区
     * @param $period_no
     * @param $advancedRreward
     */
    private function setOrdinary($period_no, $ordinaryRreward)
    {
        //计算普通矿区总和
        $advancedMoney = Db::name("betting_log")->where("period_no", $period_no)->where("status", 2)->sum("money");

        //查询所有普通矿区的用户
        $data = Db::name("betting_log")->where("period_no", $period_no)->group("mid")->where("status", 2)->field("sum(money) as totalmoney,mid")->select();
        if (empty($data)) {
            return '';
        }
        $alldata = [];
        foreach ($data as $vo) {
            if (Db::name("locked")->where("mid", $vo['mid'])->where("period_no", $period_no)->where("type", 2)->find()) {
                continue;
            }
            $totalmoney = $vo['totalmoney'];
            $income = (($totalmoney / $advancedMoney) * $ordinaryRreward);
            // 计算用户总金额
            $money = $income + $totalmoney;

            $pid = getUserInfo($vo['mid'], 'pid');
            $alldata[] = [
                'money' => $money,
                'period_no' => $period_no,
                'mid' => $vo['mid'],
                'type' => 2,
                'income' => $income,//收益
                'pid' => $pid,//上级ID
                'create_time' => time(),
                'unlock_time' => (strtotime(date("Y-m-d")) + 86400 * 5) + 1,
            ];
        }
        if (empty($alldata)) {
            return '';
        }
        Db::startTrans();
        if (Db::name("locked")->insertAll($alldata)) {
            Db::commit();
        } else {
            Log::error("插入普通矿区数据失败{$period_no}");
            Db::rollback();
        }
    }

    /**基础矿区
     * @param $period_no
     */
    public  function setBase($period_no)
    {
        //查询所有基础矿区的用户
        $data = Db::name("betting_log")->where("period_no", $period_no)->group("mid")->where("status", 3)->field("sum(money) as totalmoney,mid")->select();
        if (empty($data)) {
            return '';
        }

        foreach ($data as $vo) {
            $alldata = [];
            //如果存在跳过
            if (Db::name("locked")->where("mid", $vo['mid'])->where("period_no", $period_no)->where("type", 3)->find()) {
                continue;
            }
            $money = ($vo['totalmoney'] * 15) / 10 / 100;
            Db::startTrans();
            for ($i = 1; $i <= 100; $i++) {
                $alldata[] = [
                    'money' => $money,
                    'period_no' => $period_no,
                    'mid' => $vo['mid'],
                    'type' => 3,
                    'create_time' => time(),
                    'unlock_time' => (strtotime(date("Y-m-d")) + 86400 * $i) + 1,
                ];
            }
            if (empty($alldata)) {
                return '';
            }
            if (Db::name("locked")->insertAll($alldata)) {
                Db::commit();
            } else {
                Log::error("插入数据失败{$period_no}:用户" . $vo['mid']);
                Db::rollback();
            }
        }

    }
    public function grantMoney(){
        set_time_limit(0);
        //$time = time()+86400*5;
        $time = time();
        //发放和释放下注收益 
        $DiggingsModel = new DiggingsModel();
        $dgather_uid = env("account.gather");
        $lockeds = Db::name('locked')->where(['status'=>0])->where('type','<',3)->where('unlock_time','<',$time)->field('id,mid,sum(money) as moneys,status,type,period_no')->group("mid")->select();
        foreach ($lockeds as $key => $value) {
            $address = getUserInfo($value['mid'], "address");
            $result = $DiggingsModel->interiorTransfer($dgather_uid,$value['moneys'], $address,"locked", $value['period_no']);
            if($result === true){
                Db::startTrans();
                $result = Db::name('locked')->where(['mid'=>$value['mid']])->where('type','<',3)->where('unlock_time','<',$time)->update(['status'=>1]);
                if($result){
                    Db::commit();
                }else{
                    Log::error("修改收益状态失败:" . $value['id']);
                    Db::rollback();
                }
            }
        }
        $newlocked_uid = env("account.gather");
        $lockeds = Db::name('locked')->where(['status'=>0])->where('type',3)->where('unlock_time','<',$time)->field('id,mid,sum(money) as moneys,status,type,period_no')->group("mid")->select();
        foreach ($lockeds as $key => $value) {
            $address = getUserInfo($value['mid'], "address");
            $result = $DiggingsModel->interiorTransfer($newlocked_uid,$value['moneys'], $address,"locked", $value['period_no']);
            if($result === true){
                Db::startTrans();
                $result = Db::name('locked')->where(['mid'=>$value['mid']])->where('type',3)->where('unlock_time','<',$time)->update(['status'=>1]);
                if($result){
                    Db::commit();
                }else{
                    Log::error("修改收益状态失败:" . $value['id']);
                    Db::rollback();
                }
            }
        }
        //发放推广奖励
        $data = Db::name('promotion_rewards')->where(['status'=>0])->field('id,mid,sum(money) as moneys,status')->group("mid")->select();
        foreach ($data as $key => $value) {
            $address = getUserInfo($value['mid'], "address");
            $result = $DiggingsModel->interiorTransfer($dgather_uid,$value['moneys'], $address,"rewards");
            //var_dump($dgather_uid,$value['money'], $address,"rewards",$result);exit;
            if($result === true){
                Db::startTrans();
                $result = Db::name('promotion_rewards')->where(['mid'=>$value['mid']])->update(['status'=>1]);
                if($result){
                    Db::commit();
                }else{
                    Log::error("修改推广收益状态失败:" . $value['id']);
                    Db::rollback();
                }    
            }
        }

        //发放分红节点
        // $data = Db::name('node_bonus')->

    }

}