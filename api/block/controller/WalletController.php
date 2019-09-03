<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/20
 * Time: 10:51
 */

namespace api\block\controller;

use api\common\controller\ApiUserController;
use api\common\controller\StaticRevenueController as StaticRevenue;
use api\common\model\MembersModel as Members;
use api\common\model\StaticRevenueInterestLogsModel as SRIL;
use api\common\model\DynamicRevenueInterestLogsModel as DRIL;
use api\common\model\SocialGroupProfitLogsModel as SGPL;
use api\common\model\TokenCurrencyModel as TokenCurrency;

class WalletController extends ApiUserController
{
    public $url = 'http://120.78.141.142:10002';

    /*public function __construct()
    {
        $this->uid = 177;
    }*/

    /*
     * 测试方法
     * */
    public function test(
        Members       $Members,
        SRIL          $SRIL,
        DRIL          $DRIL,
        SGPL          $SGPL,
        TokenCurrency $TokenCurrency,
        StaticRevenue $StaticRevenue
    ) {

    }// test() end

    /*
     * 钱包首页
     * */
    public function index(
        Members       $Members,
        SRIL          $SRIL,
        DRIL          $DRIL,
        SGPL          $SGPL,
        TokenCurrency $TokenCurrency,
        StaticRevenue $StaticRevenue
    ) {
        $data = $this->walletInformation($this->uid, $Members, $SRIL, $DRIL, $SGPL, $TokenCurrency, $StaticRevenue);

        if (empty($data))
            return $this->error(lang("error"));

        $this->success($data);
    }// index() end

    /*
     * 团队管理 - 页面数据
     * */
    public function teamManagement(
        Members       $Members,
        SRIL          $SRIL,
        DRIL          $DRIL,
        SGPL          $SGPL,
        TokenCurrency $TokenCurrency,
        StaticRevenue $StaticRevenue
    ) {
        $data = $this->walletInformation($this->uid, $Members, $SRIL, $DRIL, $SGPL, $TokenCurrency, $StaticRevenue);

        // 直推人数
        $pushBeforTotal = $Members->pushBeforTotal($this->uid);
        // 社群等级人数
        $users          = $Members->pushUsers($this->uid, 'sg_level');
        // 社群管理人数
        $users2         = $Members->umbrellaUsers($this->uid);
        //help_p($users);
        // 用户当前星级
        $data['sg_level_mine']  = $Members->sgLevel($this->uid);
        $data['push']           = $pushBeforTotal;                 // 社群管理 - 直推
        $data['alliance']       = count($users2) - $pushBeforTotal;// 社群管理 - 联盟
        $data['sg_total']       = count($users2);                  // 社群管理 - 总共
        $data['sg_level_1']     = 0;                               // 社群星级 - 1 星
        $data['sg_level_2']     = 0;                               // 社群星级 - 2 星
        $data['sg_level_3']     = 0;                               // 社群星级 - 3 星

        // 统计星级人数
        foreach ($users as $val) {
            switch ($val['sg_level']) {
                case 1:
                    $data['sg_level_1'] += 1;
                    break;
                case 2:
                    $data['sg_level_2'] += 1;
                    break;
                case 3:
                    $data['sg_level_3'] += 1;
                    break;
            }
        }
        //help_p($data);

        return $this->success($data);
    }// teamManagement() end

    /*
     * 社群等级
     * */
    public function socialGroupsLevel(Members $Members)
    {
        $data = $Members->pushUsers($this->uid, 'number, sg_level', [['sg_level', '>', 0]]);
        $lang = lang('relation_1');
        foreach ($data as $key => $val) {
            $data[$key]['relation'] = $lang;
        }

        return $this->success($data);
    }// socialGroupsLevel() end

    /*
     * 社群管理
     * */
    public function socialGroupsManage(Members $Members)
    {
        $data = $Members->umbrellaUsers($this->uid);

        return $this->success($data);
    }// socialGroupsManage() end

    /*
     * 钱包信息
     * 参数1：int $tid jm_members.id
     * return array
     * */
    public function walletInformation(
        int $tid      = 0,
        Members       $Members,
        SRIL          $SRIL,
        DRIL          $DRIL,
        SGPL          $SGPL,
        TokenCurrency $TokenCurrency,
        StaticRevenue $StaticRevenue
    ) {
        $sum = 0;// 余额 = 总静态收益 + 总动态收益 + 总社群收益
        $res = $Members->one('address, profit, dynamic, sg_profit', ['id' => $tid]);

        if (empty($res))
        {
            return [];
        }

        $sum = $res['profit'] + $res['dynamic'] + $res['sg_profit'];
        //help_p('sum-'.$sum);

        // 今日静态收益
        $static_revenue  = $SRIL->one($tid);

        // 今日动态收益
        $dynamic_revenue = $DRIL->dynamicRevenueOne([
            'members_id'  => $tid,
            'create_time' => ['>', date('Y-m-d', time())]
        ]);

        // 今日社群收益
        $social_group    = $SGPL->SocialGroupProfitOne([
            'members_id'  => $tid,
            'create_time' => [ '>', date('Y-m-d', time())]
        ]);

        // creator 合约，name 币种名称
        $token  = $TokenCurrency->one(1,'price, rate, creator, name');
        // chain 余额
        $res2   = $StaticRevenue->accountBalance($res['address'], $token);
        //help_p($res2);

        // 1000 WSEC
        if (empty($res2))
        {
            $balance = 0;
        } else {
            $balance = explode(' ', $res2[0])[0];
        }

        $data = [
            'static_revenue'    => $static_revenue,  // 今日静态收益
            'dynamic_revenue'   => $dynamic_revenue, // 今日动态收益
            'social_group'      => $social_group,    // 今日社群收益
            'sum'               => $sum,             // 总收益
            'balance'           => $balance,         // chain 余额
            'address'           => $res['address'],  // 钱包地址
        ];

        //help_p($data);
        return $data;
    }// walletInformation() end

    /*
     * 我的 - 页面数据
     * */
    public function mine(Members $Members)
    {
        $res = $Members->one('number, mobile', ['id' => $this->uid]);

        if (empty($res))
            return $this->error(lang("error"));

        $url  = $this->url . "/index/index/zingdapp/pid/" . $this->uid;
        $data = [
            'number'            => $res['number'],
            'mobile'            => $res['mobile'],
            'invitation_code'   => $url
        ];

        return $this->success($data);
    }// mine() end
}// WalletController{} end