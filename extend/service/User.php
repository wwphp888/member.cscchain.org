<?php

namespace service;

use system\Curl;

/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/14
 * Time: 11:43
 */
class User
{
    //用户服务地址
    const url = 'http://192.168.1.154:888/';
    protected static $instance = null;
    const project = 'user';
    protected $error = null;
//密钥
    const appSecret = 'hiwa&$%ehkipo@asqw';
    //加密方式
    protected static $sign_method = 'sha256';

    /**
     * @annotate 单例
     * @author 江枫
     * @email 635449961@qq.com
     * @url:www.cloudcmf.com
     * @time
     */
    public static function instance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 获取用户ID
     */
    public function getUid()
    {
        $reids = redis();
        $token = request()->header("token");
        if (empty($token)) {
            $this->error = "token不存在";
            return false;
        }
        $uid = $reids->get("members:" . $token);
        if (empty($uid)) {
            $this->error = "用户没有登陆";
            return false;
        }
        return $uid;
    }

    /**检查手机号
     * @param $mobile
     * @return bool
     */
    public function checkMobile($mobile)
    {
        $data['mobile'] = $mobile;
        $res = $this->servicePost("service/index/checkMobile", $data);
        if ($res["code"] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**获取用户信息
     * @param $mobile
     * @return bool
     */
    public function getUserInfo($uid)
    {
        $data['uid'] = $uid;
        $res = $this->servicePost("service/index/getUserInfo", $data);
        if ($res["code"] == 1) {
            return $res;
        } else {
            return false;
        }
    }

    /** 签名post
     * @param $data
     * @return mixed
     */
    public function servicePost($url, $data)
    {
        $data['project'] = env("project");
        $data['timetamp'] = date("YmdHis");
        $data['sign'] = $this->getSign($data);
        $curl = new Curl();
        $info = $curl->post(self::url . $url, $data);
        return json_decode($info, true);

    }

    /** 获取签名
     * @param $params
     * @return string
     */
    protected function getSign($params)
    {
        unset($params['sign']);
        ksort($params);
        $tmps = array();
        foreach ($params as $k => $v) {
            $tmps[] = $k . $v;
        }
        $string = implode('', $tmps) . self::appSecret;
        return strtolower(hash(self::$sign_method, $string));
    }
}