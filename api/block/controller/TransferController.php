<?php
/** 转账控制器
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/17
 * Time: 19:14
 */

namespace api\block\controller;

use api\common\controller\ApiUserController;
use api\common\model\MedalModel;
use api\common\model\SunTraccount;
use service\Block;
use think\Db;
use think\facade\Env;

class TransferController extends ApiUserController
{

    /**
     * 大洲交易
     *
     */
    public function region_transaction()
    {
        set_time_limit(0);

        $region_id = intval($this->data['region_id']??0);
        if (empty($region_id)) {
            return $this->error(lang("error"));
        }
        //判断洲
        $region = getRegion($region_id);
        if (empty($region)) {
            return $this->error(lang("error"));
        }
        //洲的地址
        $address = $region['address'];
        if (empty($address)) {
            return $this->error(lang("error"));
        }

        $uid = $this->uid;
        $where['id'] = $uid;
        $user = dbMember("members")->where($where)->find();
        //禁用用户
        if ($user['is_dis_award'] == 1) {
            return $this->error(lang("account_frozen"));
        }
        $traccount_robot_time = session('region_transaction');
        if (!empty($traccount_robot_time) && $traccount_robot_time > time() - 2) {
            return $this->error(lang('operating_fast'));
        } else {
            if ($traccount_robot_time < time() - 10) {
                session('region_transaction_' . $uid, "end");
            }
            session('region_transaction', time());
        }
        $period = Db::name("period")->where("status", 0)->find();
        if (empty($period)) {
            return $this->error(lang('already_closed'));
        }
        //封盘
        if ($period['end_time'] < time()) {
            return $this->error("Mining inputs are banned！矿区投入禁止！");
        }
        $period_no = $period['period_no'];
        //币种ID ,强制
        $currency_id = 1;
        if ($this->request->isPost()) {
            $data = $this->data;
            $payPwd = cmf_password($data['payPassword']);
            $memberPay = $user['trade_pwd'];
            //判断交易密码
            if ($payPwd != $memberPay) {
                session('region_transaction_' . $uid, "end");
                return $this->error(lang("transaction_password_6"));
            }
            //判断用户是否还在交易
            $traccount_start = session('region_transaction_' . $uid);
            if ($traccount_start == 'run') {
                return $this->error(lang("lease_wait_patiently"));
            }

            session('region_transaction_' . $uid, "run");
            $block = new Block();
            //获取链上zing
            $have_money = $block->getUserZing($uid);
            if ($data['money'] < 100) {
                session('region_transaction_' . $uid, "end");
                return $this->error("每次最少投入100个WSEC");
            }
            //如果转入金额大于可用
            if ($data['money'] > $have_money) {
                session('region_transaction_' . $uid, "end");
                return $this->error(lang('insufficient_of'));
            }
            //判断格式,并且不大于0
            if (!preg_match('/^[0-9]+(.[0-9]{1,8})?$/', $data['money']) || !($data['money'] > 0)) {
                session('region_transaction_' . $uid, "end");
                return $this->error(lang('input_error'));
            }
            $poundage = 0;

            //投入的钱
            $money = $data['money'];
            $ordersn = makeOrderNo("KQ");
            $data = array(
                "ordersn" => $ordersn,
                'mid' => $user['id'],
                'money' => $money,
                'true_money' => $money,
                'poundage' => $poundage,
                'address' => $address,
                'remark' => "region " . $region['abb'],
                'create_time' => time(),
                'update_time' => time(),
                'status' => 0,
                'is_handle' => 1,
                'is_app' => 1,
                'currency_id' => $currency_id,
                "transaction_id" => "--",
                'status_type' => 2,
                'period_no' => $period_no,
            );
            $res = Db::name("sun_traccount")->insertGetId($data);
            session('region_transaction_' . $uid, "end");
            if ($res) {
                $SunTraccount = new SunTraccount();
                $return = $block->transaction($res);
                //如果是false 直接失败
                if ($return === false) {
                    return $this->error(lang('bet_failed'));
                }
                if (isset($return['status']) && $return['status'] == 1) {
                    //更新转账记录
                    Db::name("sun_traccount")->where(array("id" => $res))->update(array("status" => 1, "transaction_id" => $return['transaction_id'], "block_num" => $return["block_num"], "return_msg" => json_encode($return)));
                    //添加日志
                    $SunTraccount->add_zing_log($uid, 2, $money);
                    //添加投注记录
                    $this->add_betting_log($res, $data);
                    return $this->success("Invest mine success! 矿区投入成功！");
                } else {
                    Db::name("sun_traccount")->where(array("id" => $res))->update(array("status" => 2, "is_app" => 0, "return_msg" => $return));
                    return $this->error(lang('bet_failed'));
                }
            } else {
                return $this->error("系统错误");
            }

        }
    }

    /**添加投注日志
     * @param $id
     * @param $data
     */
    private function add_betting_log($id, $data)
    {
        $Medal = new MedalModel();
        //添加勋章资金
        if ($Medal->addMedalMoney($this->uid, $data['money']) === false) {
            return false;
        }
        $data = [
            'tid' => $id,
            'mid' => $this->uid,
            'period_no' => $data['period_no'],
            'money' => $data['money'],
            'create_time' => time(),
            'region_id' => $this->data['region_id'],
        ];
        if (!Db::name("betting_log")->insert($data)) {
            return false;
        }
    }

    /**
     * 转账交易，可以上交易所
     */
    public function traccount_submit()
    {
        set_time_limit(0);

        /*$this->data = [
            'money'         => 1,// 转账金额
            'address'       => 'ror4ae53phsm',// 收款
            'payPassword'   => '123456',// 交易密码
            'remark'        => 'test' // 备注
        ];*/

        $uid = $this->uid;// 获取 jm_members.id
        $where['id'] = $uid;
        $user = dbMember("members")->where($where)->find();

        // 制定币种 ID，1 是 WSEC
        $currency_id = 1;

        if ($this->request->isPost())
        {
            // 转账限制在 3 秒一次【防刷】
            $redisKey = Env::get('project').':members:traccount_submit:limit:'.$uid;
            if (redis()->has($redisKey)) {
                if (redis()->get($redisKey) > time()) {
                    return $this->error(lang('transaction_1'));
                }
            } else {
                redis()->set($redisKey, (time()+3), 3);
            }

            $data       = $this->data;
            $payPwd     = cmf_password($data['payPassword']);
            $memberPay  = dbMember('members')
                            ->where('id', $uid)
                            ->value('trade_pwd');

            //判断交易密码
            if ($payPwd != $memberPay)
            {
                session('traccount_' . $uid, "end");

                return $this->error(lang("transaction_password_6"));
            }

            //判断备注不能为空
            if (empty($data['remark']))
            {
                session('traccount_' . $uid, "end");

                return $this->error(lang("please_enter_notes"));
            }

            //判断备注格式
            if (preg_match('/[a-zA-Z\x{4e00}-\x{9fa5}]/u', $data['remark']) === false)
            {
                return $this->error(lang('input_1'));
            }
            if (strlen($data['remark']) > 255)
            {
                return $this->error(lang('remark_1'));
            }

            session('traccount_' . $uid, "run");

            $block      = new Block();
            //获取链上 zing
            $have_money = $block->getUserZing($uid);
            $c_name     = "WSEC";

            // $data['money'] 转账金额，并且小数位必须保证 4 位
            // 转账金额需大于余额
            if ($data['money'] > $have_money)
            {
                session('traccount_' . $uid, "end");

                return $this->error(lang('insufficient_of'));
            }

            if (!preg_match('/^[0-9]+(.[0-9]{1,4})?$/', $data['money']) || !($data['money'] > 0))
            {
                session('traccount_' . $uid, "end");

                return $this->error(lang('input_error'));
            }

            // 转账地址
            $data['address'] = trim($data['address']);

            if (empty($data['address']))
            {
                session('traccount_' . $uid, "end");

                return $this->error(lang('message_6'));
            }

            // 地址长度限制
            if (strlen($data['address']) > 12)
                return $this->error(lang('message_7'));

            // 地址不存在
            $u = Db::name('members')->where('address', $data['address'])->field('id')->find();
            if (empty($u))
                return $this->error(lang('message_8'));

            // 获取手续费
            $poundage   = $this->config['traccount_ratio']??1;
            // 到账金额
            $true_money = $data['money'];

            if ($poundage > 0 && !in_array($uid, config("site.IDS")))
            {
                $poundage   = round($data['money'] * $poundage / 100, 8);// 手续费计算
                $true_money = round($data['money'] - $poundage, 8);// 计算手续费后的转账金额
            } else {
                $poundage = 0;
            }

            $time       = strtotime(date("Ymd"));
            $ids        = config("site.IDS"); //不限制
            $ordersn    = makeOrderNo("GM");

            $data       = [
                "ordersn"           => $ordersn,
                'mid'               => $user['id'],
                'money'             => $data['money'],
                'true_money'        => $true_money,
                'poundage'          => $poundage,
                'address'           => $data['address'],
                'remark'            => $data['remark'],
                'create_time'       => time(),
                'update_time'       => time(),
                'status'            => 0,
                'is_handle'         => 1,
                'is_app'            => 1,
                'status_type'       => 1,
                'currency_id'       => $currency_id,
                "transaction_id"    => "--",
            ];

            // 插入记录并获取自增ID
            $res = Db::name("sun_traccount")->insertGetId($data);

            if ($res)
            {
                $SunTraccount   = new SunTraccount();
                // 进行转账, $uid 是付款人 jm_members.id
                $return         = $block->transaction($res, $uid);

                //如果是false 直接失败
                if ($return === false)
                {
                    Db::name("sun_traccount")
                        ->where(array("id" => $res))
                        ->update(array("status" => 2, "is_app" => 0, "return_msg" => 'false'));

                    session('traccount_' . $uid, "end");

                    return $this->error(lang('failed_transfer'));
                }

                if (isset($return['status']) && $return['status'] == 1)
                {
                    // 转账成功后的执行
                    Db::name("sun_traccount")
                        ->where(array("id" => $res))
                        ->update([
                            "status"            => 1,
                            "transaction_id"    => $return['transaction_id'],
                            "block_num"         => $return["block_num"],
                            "return_msg"        => json_encode($return)
                        ]);

                    $info = Db::name("sun_traccount")
                        ->where(array("id" => $res))
                        ->find();

                    $SunTraccount->add_zing_log($uid, 1, $info['money']);

                    if ($poundage > 0)
                    {
                        $SunTraccount->poundage_log($uid, $info['id'], $poundage, 2);
                    }

                    session('traccount_' . $uid, "end");

                    return $this->success(lang('successful_transfer'));
                } else {
                    Db::name("sun_traccount")->where(array("id" => $res))->update(array("status" => 2, "is_app" => 0, "return_msg" => $return));
                    session('traccount_' . $uid, "end");

                    return $this->error(lang('failed_transfer'));
                }
            } else {
                session('traccount_' . $uid, "end");

                return $this->error(lang('error'));
            }
        }
    }// traccount_submit() end


    //新转账记录
    public function transaction_list($page)
    {
        set_time_limit(0);
        $uid            = $this->uid;
        $data           = $this->data;
        $currency_id    = 1;
        $time           = session("transaction_list_" . $uid);
        $address = Db::name("members")->where(['id'=>$uid])->value('address');
        if ($page == 1 && (time() - $time) > 10)
        {
            session("transaction_list_" . $uid, time());

            $Block = new Block();
            $Block->get_transfer_list($uid, $page, $currency_id);
        }

        $where = array("mid" => $uid);
        $whereor = array("address" => $address);

        $where['currency_id'] = $currency_id;

        $list = Db::name('sun_traccount')
                    ->where("(mid=:uid or address=:address) and currency_id=:currency_id", ['uid' => $uid , 'address' => $address,'currency_id'=>$currency_id])
                    ->field("id,status,type as transact_type,create_time,address,money")
                    ->order('create_time desc,id desc')
                    ->page($page, 10)
                    ->select()
                    ->toArray();

        foreach ($list as $k => $v) {
            $v['money'] = ($v['money'] * 1) . "";

            if ($v['transact_type'] == 1)
            {
                
            } else {
              
            }
            if($v['address']==$address){
            	$v['transact_type']=2;
            	$type_name = lang("receive");
            }else{
            	$v['transact_type']=1;
            	$type_name = lang("transfer");
            }
            $status = $v['status'] == 1 ? lang("successfully") : ($v['status'] == 2 ? lang("failed") : lang("submitting"));

            $v['status_name']   = $type_name . $status;
            $v['create_time']   = date("Y-m-d H:i:s", $v['create_time']);
            $list[$k]           = $v;
        }
        return $this->success("成功", $list);
    }// transaction_list() end

    /**
     * 转账详情
     */
    public function transaction_detail()
    {
        $data   = $this->data;
        $id     = $data['id'];

        if (empty($id))
            $this->error(lang('error'));

        $info = Db::name("sun_traccount")
                    ->where(array("id" => $id))
                    ->where("mid", $this->uid)
                    ->find();

        if (empty($info))
            $this->error(lang('error'));

        $currency_info = Db::name("token_currency")
                            ->where(array("id" => $info['currency_id']))
                            ->find();

        $address    = getUserInfo($info['mid'], 'address');
        $lang       = $this->lang;
        $trac_data  = array();

        $trac_data['money'] = ($info['money'] * 1) . " " . $currency_info['name'];
        $trac_data['type']  = $info['type'];

        if ($info['type'] == 1) {
            $trac_data['from_address']  = $address;
            $trac_data['to_address']    = $info['address'];
        } else {
            $trac_data['to_address']    = $address;
            $trac_data['from_address']  = $info['address'];
        }

        $trac_data['remark']            = $info['remark'];
        $trac_data["transaction_id"]    = $info['transaction_id'];
        $trac_data['create_time']       = date("Y-m-d H:i:s", $info['create_time']);
        $trac_data['status']            = $info['status'];

        if ($info['type'] == 1)
        {
            $type_name = lang('transaction_2');
        } else {
            $type_name = lang('transaction_3');
        }

        $type1  = lang('success');
        $type2  = lang('fail');
        $type3  = lang('success');
        $status = $trac_data['status'] == 1 ? "$type1" : ($trac_data['status'] == 2 ? "$type2" : "$type3");

        $trac_data['status_name'] = $type_name . $status;

        return $this->success(lang('transaction_4'), $trac_data);
    }
}