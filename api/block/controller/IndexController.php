<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/16
 * Time: 20:18
 */

namespace api\block\controller;


use api\common\controller\ApiUserController;
use api\common\model\MedalModel;
use api\common\model\RegionModel;
use api\common\model\SunTraccount;
use service\Block;
use think\Db;
use api\common\model\PeriodModel;

class IndexController extends ApiUserController
{
    /**
     * 系统首页
     */
    public function index()
    {
        $userinfo = dbMember("members")
                    ->where("id", $this->uid)
                    ->field("address,mobile,number")
                    ->find();

        if (empty($userinfo))
            $this->error(lang('members_3'));

        if (empty($userinfo['address']))
        {
            $param = [
                'mobile' => $userinfo['mobile']
            ];

            $url = request()->domain();
            curl()->post($url . "/api.php/block/suntoken/create_account", $param);

            //再拉取一次用户信息
            $userinfo = dbMember("members")
                        ->where("id", $this->uid)
                        ->field("address,mobile,number")
                        ->find();
        }

        $sun_account = Db::name("sun_account")
                        ->where("mid",$this->uid)
                        ->find();

        if(empty($sun_account))
        {
            $sun_account = [
                'mid'           => $this->uid,
                'usdt_balance'  => 0,
                'btc_balance'   => 0,
                'eos_balance'   => 0,
                'eth_balance'   => 0
            ];

            Db::name("sun_account")->insert($sun_account);
        }

        $block = new Block();

        $data                   = $userinfo;
        $data['id']             = $userinfo['number'];
        $markerPrices           = get_price();
        $data['usdt_balance']   = $sun_account['usdt_balance'];
        $data['btc_balance']    = $sun_account['btc_balance'];
        $data['eth_balance']    = $sun_account['eth_balance'];
        $data['eos_balance']    = $sun_account['eos_balance'];
        $data['usdt_price']     = $markerPrices['usdt'];
        $data['btc_price']      = $markerPrices['btc_usdt'];
        $data['eth_price']      = $markerPrices['eth_usdt'];
        $data['eos_price']      = $markerPrices['eos_usdt'];
        $data['zing_price']     = $markerPrices['zing_usdt'];

        $RegionModel = new RegionModel();
        //上一期奖
        $PeriodModel = new PeriodModel;
        $period = $PeriodModel->getPrevious();


        $data['open_lttery']     = $period['period'];
        $data['is_open_lttery']  = $period['period']['btc_usdt'] == '?' ? false : true;
        $data['open_lttery_msg'] = "Mining inputs are banned！矿区投入禁止！" ;//lang("already_closed")

        //投注
        $data['betting'] = $RegionModel->getTotalAmount();
        //获取销毁账户
        $destroy_uid              = env("account.destroy");
        $totalDestruction         = $block->getUserZing($destroy_uid, 600);
        $data['todayDestruction'] = floatval($period['todayDestruction']).'';
        $data['totalDestruction'] = $totalDestruction.'';

        //zing

        //可用zing
        $zing            = $block->getUserZing($this->uid);
        $lockzing        = 0;
        $data['balance'] = ($zing + $lockzing) . '';
        $MedalModel      = new MedalModel();
        $medal           = $MedalModel->getMedal($this->uid, 'medal');
        $data['medal']   = $medal??0;

        return $this->success("成功", $data);
    }

    /**
     * 获取开奖列表
     */
    public function getLotteryLists()
    {
        $Period = new PeriodModel;
        $data = $Period
                ->where("status", 1)
                ->field("period_no,advanced")
                ->cache(600)
                ->limit(0, 10)
                ->order("period_no desc")
                ->select();

        if (!empty($data))
        {
            foreach ($data as $key => $v) {
                $region            = getRegion($v['advanced']);
                $data[$key]['abb'] = $region['abb'];
            }
        }

        return $this->success("成功", $data);
    }

    /**
     * 获取我的预测
     */
    public function getMyPrediction()
    {
        $Period    = new PeriodModel;
        $period_no = $Period->getThisPeriod();

        $data = Db::name("betting_log")
                    ->where("mid", $this->uid)
                    ->where("period_no", $period_no)
                    ->group("region_id")
                    ->field("SUM(money) as money,region_id")
                    ->select()
                    ->toArray();

        foreach ($data as $key => $vo) {
            $region               = getRegion($vo['region_id']);
            $data[$key]['enname'] = $region['enname'];
        }

        return $this->success($data);
    }

    /**
     * 获取规则
     */
    public function getRule()
    {
        $content = Db::name('news')
                    ->where("id", 2)
                    ->cache(60)
                    ->value("content");

        //规则
        $data['rule'] = strip_tags(str_replace("</p><p>", "\n", $content));

        return $this->success($data);
    }

    /**
     * 获取广播
     */
    public function getBroadcast()
    {
        $news = Db::name('news')
                    ->where("id", 1)
                    ->cache(60)
                    ->find();

        //规则
        $data['broadcast'] = strip_tags(str_replace("</p><p>", "\n", $news['content']));
        $data['news_img']  = empty($news['news_img']) ? '' : str_replace('/api.php','',cmf_get_image_preview_url($news['news_img']));
        $url               = $_SERVER['SERVER_NAME'];
        $data['url']       = "http://".$url."/index/index/getbroadcast";

        return $this->success($data);
    }
}