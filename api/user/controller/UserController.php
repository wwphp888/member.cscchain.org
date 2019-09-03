<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 15:57
 */

namespace api\user\controller;

use api\common\controller\ApiController;
use api\common\Model\MemberMoneyModel;
use service\User;
use think\Db;

//后面删除掉1
use service\Block;
use api\common\model\PeriodModel;
use api\common\model\RegionModel;
use api\common\model\MedalModel;
use api\common\model\SunTraccount;
use api\common\model\LockedModel;

use api\common\controller\ApiUserController;

use Eos\Client;
use Eos\Ecc;


class UserController extends ApiController
{
    public function share(){}

    /**
     * 发送验证码
     */
    public function sendMsg()
    {
        $mobile     = trim($this->data['mobile']??'');
        $num        = trim($this->data['num']??'');
        $is_mobile  = isMobile($mobile);//验证是否是手机

        if (!$is_mobile) {
            return $this->error(lang("phone_number_error"));
        }
        if (empty($this->data['type'])) {
            return $this->error(lang("error"));
        }

        //如果是注册验证手机号是否注册过
        if ($this->data['type'] == 'reg') {
            $id = dbMember("members")->where("mobile", $mobile)->field("id")->find();
            if ($id) {
                return $this->error(lang('phone_presence'));
            }
        }
        if ($this->data['type'] == 'forget') {
            $id = dbMember("members")->where("mobile", $mobile)->field("id")->find();
            if (empty($id)) {
                return $this->error(lang('phone_error_match'));
            }
        }

        $res = sendMessage($mobile, $num, $this->data['type']);

        if ($res['code'] == 0) {
            return $this->error($res['message']);
        }
        return $this->success(lang("sms_send_success"));
    }

}