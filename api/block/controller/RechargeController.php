<?php
/**
 * Created by PhpStorm.
 */

namespace api\block\controller;


use api\common\controller\ApiUserController;
use api\common\model\LockedModel;
use service\Block;
use think\Db;

class RechargeController extends ApiUserController
{
    /**
     * 充值信息
     */
    public function index($cid=1, $type=0)
    {
        $address = '';
        $mobile  ='';

        $info = Db::name("members")
                    ->where("id",$this->uid)
                    ->field("number,address")
                    ->find();

        $remark = substr($info['address'],-3,0).$info['number'];

        switch ($cid){
            case 1:
                $address = '';
                $mobile  = '15290376799';
                $remark  = '';
                break;
            case 2:
                $address = '';
                $mobile  = '15290376799';
                $remark  = '';
                break;
            case 3:
                $address = '';
                $mobile  = '15290376799';
                $remark  = '';
                break;
            case 4:
                $address = 'zingdappeos1';
                $mobile  = '15290376799';
                $remark  = $remark;
                break;
        }

        $user_address = '';

        $info = Db::name("sun_recharge_address")
                    ->where(['mid'=>$this->uid,'is_default'=>1])
                    ->find();

        if(empty($info)){
            $info = Db::name("sun_recharge_address")
                        ->where(['mid'=>$this->uid])
                        ->find();

            if(!empty($info)){
                $user_address = $info['address'];
            }
        }else{
            $user_address = $info['address'];
        }

        $data = [
            'address'       => $address,
            'mobile'        => $mobile,
            "user_address"  => $user_address,
            "remark"        => $remark
        ];

        if($type>0){
            return $data;
        }

        $this->success($data);
    }

    /**
     * 提交充值申请单
     */
    public function add()
    {
        if ($this->request->isPost())
        {
            $data       = $this->data;
            $address    = trim($data['address']);
            $cid        = $data['cid'];
            $type       = $data['type'];//是否是ocx交易所1是2不是
            $money      = $data['money'];

            if(empty($cid) || empty($type) || empty($address))
            {
                $this->error("信息错误");
            }

            $payPwd      = cmf_password($data['payPassword']);
            //判断交易密码
            $where['id'] = $this->uid;
            $user        = dbMember("members")->where($where)->find();
            $memberPay   = $user['trade_pwd'];

            if ($payPwd != $memberPay) {
                return $this->error(lang("transaction_password_error"));
            }
            if (!preg_match('/^[0-9]+(.[0-9]{1,8})?$/', $data['money']) || !($data['money'] > 0)) {
                return $this->error("数量输入有误");
            }
            if($type == 1 && !isMobile($address) && !isEmail($address))
            {
                $this->error("使用OCX交易所,请填写您在交易所注册的手机号或者邮箱!".$address);
            }

            $cc = Db::name("sun_recharge")
                    ->where(['address'=>$address,'status'=>'0'])
                    ->where("mid","<>",$this->uid)
                    ->find();

            if($cc){
                $this->error("该地址正在被其他用户使用,请更换地址!");
            }

            $account_data= $this->index($cid,$type);

            if($type==1)
            {
                $account = $account_data['mobile'];
            }else{
                $account = $account_data['address'];
            }
            if(empty($account)){
                $this->error("信息错误");
            }

            $ordersn = makeOrderNo("RE");

            $recharge_order = [
                'mid'           => $this->uid,
                'cid'           => $cid,
                'type'          => $type,
                'ordersn'       => $ordersn,
                'address'       => $address,
                'money'         => $money,
                'remark'        => $account_data['remark'],
                'account'       => $account,
                'status'        => 0,
                'create_time'   => time(),
                'update_time'   => time()
            ];

            $res = Db::name("sun_recharge")->insertGetId($recharge_order);

            if($res)
            {
                $this->success("充值单提交成功",['id'=>$res]);
            }else{
                $this->error("充值单提交失败");
            }
        }
    }// add() end

    public function info($id)
    {
        if(empty($id))
        {
            $this->error("信息错误");
        }

        $status_data   = [
            'Recharging',
            'Recharge success',
            'Recharge error'
        ];
        $status_data_cn = [
            '充值中',
            "充值成功",
            "充值失败"
        ];
        $info = Db::name("sun_recharge")
                    ->where("id",$id)
                    ->field("address,money,account,status,create_time,remark")
                    ->find();

        if(!empty($info))
        {
            $info['create_time']    = date("Y-m-d H:i:s",$info['create_time']);
            $info['money']          = round($info['money'],5)."";
            $info['type_name']      = $status_data[$info['status']];
            $info['type_name_cn']   = $status_data_cn[$info['status']];
        }

        $this->success($info);
    }

    public function recharge_list($cid = '', $page = 1)
    {
        $list           = [];
        $status_data    = [
            'Recharging',
            'Recharge success',
            'Recharge error'
        ];
        $status_data_cn = [
            '充值中',
            "充值成功",
            "充值失败"
        ];

        if(!empty($cid))
        {
            $list = Db::name("sun_recharge")
                        ->field("id,cid,address,money,account,status,create_time")
                        ->where([
                            "mid" => $this->uid,
                            "cid" => $cid
                        ])
                        ->order('create_time desc')
                        ->page($page, 10)
                        ->select()
                        ->toArray();

            foreach ($list as $k => &$v) {
                $v['create_time']   = date("Y-m-d H:i:s", $v['create_time']);
                $v['money']         = round($v['money'],5)."";
                $v['type_name']     = $status_data[$v['status']];
                $v['type_name_cn']  = $status_data_cn[$v['status']];
            }
        }

        $this->success($list);
    }
}