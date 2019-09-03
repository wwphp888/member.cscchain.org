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
class RegistrationController extends Controller
{
    public function index(){
        return $this->fetch();
    }

    public function add(){
        $data = input();
        if($data['phone']){
            $info = Db::name("registration")->where("phone",$data['phone'])->find();
            if(!empty($info)){
                $this->error("该手机号已报过名,请勿重复报名");
            }
            $data = [
                'phone'=>$data['phone'],
                'wxcard'=>$data['wxcard'],
                'company'=>$data['company'],
                'position'=>$data['position'],
                'email'=>$data['email'],
                'participation'=>$data['participation'],
                'create_time'=>time()
            ];
            $res = Db::name("registration")->insert($data);
            if($res){
                $this->success("报名表提交成功，感谢您的参与!");
            }
        }
        $this->success("报名表提交失败!");
    }
}