<?php
/*
 * 社群收益
 *
 * 执行社群收益之前要先执行静态收益，
 * 然后再执行以下两个函数
 * app\admin\model\MembersModel->socialGroupsLevel() 会员社群星级自动升降
 *
 * 社群
 * 一星社群：直推 5 人，10%
 * 二星社群：直推 50 个一星社群，20%
 * 三星社群：直推 100 个一星社群，30%
 *
 * 推荐关系：A -> B -> C -> D -> E
 * 社群星级：3 -> 3 -> 2 -> 1 -> 0
 *
 * 社群收益-计算公式
 * D = E * 10%
 * C = (D + E) * (20% - 10%)
 * B = (C + D + E) * (30% - 20%)
 * A = (B + C + D + E) * 10%
 *
 *
 *
 * 三星社群可以平一代，与一代分 10% 的收益
 * 三星社群伞下有两个三星，该用户出局【全网只能存在两个三星社群】
 *
 * 作者：ck
 * */

namespace app\admin\controller;

use think\Db;
use app\common\controller\AdminBaseController;
use app\admin\model\MembersModel as Members;
use app\admin\model\SocialGroupProfitLogsModel as ProfitLogs;
use app\admin\model\StaticRevenueInterestLogsModel as InterestLogs;
use app\admin\model\SocialGroupTaskLogsModel as TaskLogs;

class SocialGroupsController extends AdminBaseController
{
    protected $uids     = [8];  // 黑名单用户 ID

    public function __construct()
    {
    }

    public $user  = [
        'members_id'        => 0, // 社群收益者 ID
        't_level'           => 0, // 社群收益者 星级
        't_profit'          => 0, // 社群收益结算
        'from_members_id'   => 0, // 社群收益来源者 ID
        'from_profit'       => 0  // 社群收益基数
    ];

    // 各星级收益累计数量
    public $sgLevelTotal = [
        1 => 0,// 1 星级
        2 => 0,// 2 星级
        3 => 0,// 3 星级
    ];

    // 收益拨比总量
    public $profitPoint  = 0.4;

    /*
     * 社群星级 - 晋升
     * */
    public function test()
    {
        //$this->task();
    }// test() end

    /*
     * 社群收益定时任务 - 执行
     * */
    public function task()
    {
        $TaskLogs = new TaskLogs();
        // 防止当天重复计算社群收益
        if ($TaskLogs->today()) {
            $TaskLogs->addLogs('stop');
            return null;
        }

        $InterestLogs = new InterestLogs();
        $Members      = new Members();

        // 获取当天的静态收益
        $res = $InterestLogs->all();
        //help_p('当天的静态收益');
        //help_p($res);

        $insert   = [];       // 待写入数据库的社群收益
        $lnArr    = [1, 2, 3];

        foreach ($res as $val) {
            //help_p($val['members_id']);// jm_members.id
            //help_p($val['t_profit']);  // 静态收益
            //help_p('产生静态收益的ID-'.$val['members_id']);

            // 获取用户的上级所有会员
            $beforUsers = $Members->beforUsers($val['members_id'], ['is_dis_award' => 0, 'is_disabled' => 0]);
            //help_p('上级所有会员');
            //help_p($beforUsers);

            // 获取 $val['members_id'] 用户的星级
            $afterUser = $Members->one('id, sg_level', ['id' => $val['members_id']]);

            if (empty($afterUser))
                continue;

            foreach ($beforUsers as $key => $val2) {
                //help_p($val2);// jm_members.id 上级用户 id

                // 获取上级用户的星级
                $beforUser = $Members->one('id, sg_level', ['id' => $val2]);

                if (empty($beforUser))
                    continue;

                // 跳出黑名单用户
                if (in_array($beforUser['id'], $this->uids))
                    continue;

                //help_p('beforUser-'.$beforUser.'; afterUser-'.$afterUser);

                // 540-416-415-408-54-52-44-37-28-27-26-18-13-8
                // 星级大于 0 才会计算社群收益
                if (in_array($beforUser['sg_level'], $lnArr) === false)
                    continue;

                //help_p($val['members_id']);// 收益来源 ID
                //help_p($val['t_profit']);  // 静态收益
                switch ($beforUser['sg_level']) {
                    case 1:
                        if ($this->sgLevelTotal[1] > 0)
                        {
                            // >0 说明前面已经有人获取了 1 星等级的奖励了，所以跳出此次收益
                            break;
                        } elseif ($this->sgLevelTotal[2] > 0) {
                            // >0 说明前面已经有人获取了 2 星等级的奖励了，所以跳出此次收益
                            break;
                        } elseif ($this->sgLevelTotal[3] > 0) {
                            // >0 说明前面已经有人获取了 3 星等级的奖励了，所以跳出此次收益
                            break;
                        } elseif ($key == 0 && $beforUser['sg_level'] == $afterUser['sg_level']) {
                            // 1 == 1，所以跳出此次收益
                            $this->sgLevelTotal[1] += 1;
                            $this->profitPoint -= 0.1;
                            break;
                        } elseif ($afterUser['sg_level'] >= $beforUser['sg_level']) {
                            // 收益来源者星级 >= 收益者星级
                            if ($afterUser['sg_level'] == 1)
                            {
                                // 收益来源者星级 == 1
                                $this->sgLevelTotal[1] += 1;
                                $this->profitPoint -= 0.1;
                            } elseif ($afterUser['sg_level'] == 2)
                            {
                                // 收益来源者星级 == 2
                                $this->sgLevelTotal[2] += 1;
                                $this->profitPoint -= 0.2;
                            } elseif ($afterUser['sg_level'] == 3) {
                                // 收益来源者星级 == 3
                                $this->sgLevelTotal[3] += 1;
                                $this->profitPoint -= 0.3;
                            }

                            break;
                        } elseif ($afterUser['sg_level'] == 0) {
                            // 收益来源星级 < 收益者星级
                            // 0 < 1
                            $this->sgLevelTotal[1] += 1;
                            $this->profitPoint -= 0.1;
                        }

                        $profit   = sprintf("%.4f", ($val['t_profit'] * 0.1));;

                        $insert[] = [
                            'members_id'        => $beforUser['id'],       // 社群收益者 ID
                            't_level'           => $beforUser['sg_level'], // 社群收益者 星级
                            't_profit'          => $profit,                // 社群收益 结算
                            't_rate'            => 0.1,                    // 社群收益 结算比率
                            'from_members_id'   => $val['members_id'],     // 社群收益来源者 ID
                            'from_profit'       => $val['t_profit'],       // 社群收益来源 基数
                            'from_level'        => $afterUser['sg_level']  // 社群收益来源者 星级
                        ];

                        break;
                    case 2:
                        if ($this->sgLevelTotal[2] > 0)
                        {
                            // >0 说明前面已经有人获取了 2 星等级的奖励了，所以跳出此次收益
                            break;
                        } elseif ($this->sgLevelTotal[3] > 0) {
                            // >0 说明前面已经有人获取了 3 星等级的奖励了，所以跳出此次收益
                            break;
                        } elseif ($key == 0 && $afterUser['sg_level'] >= $beforUser['sg_level']) {
                            if ($afterUser['sg_level'] == 2)
                            {
                                // 2 == 2
                                $this->sgLevelTotal[2] += 1;
                                $this->profitPoint -= 0.2;
                            } elseif ($afterUser['sg_level'] == 3) {
                                // 3 > 2
                                $this->sgLevelTotal[3] += 1;
                                $this->profitPoint -= 0.3;
                            }

                            break;
                        } elseif ($afterUser['sg_level'] <= 2) {

                            if ($afterUser['sg_level'] == 0)
                            {
                                if ($this->sgLevelTotal[1] > 0)
                                {
                                    // 收益者来源星级 == 0，已经计算了 1 星级的收益
                                    $this->profitPoint -= 0.1;
                                    $rate = 0.1;
                                } else {
                                    // 收益者来源星级 == 0，未计算了 1 星级的收益
                                    $this->profitPoint -= 0.2;
                                    $rate = 0.2;
                                }

                            } elseif ($afterUser['sg_level'] == 1) {
                                // 收益者来源星级 == 1
                                $this->profitPoint -= 0.2;
                                $rate = 0.1;
                            } elseif ($afterUser['sg_level'] == 2) {
                                // 收益者来源星级 == 2，平级
                                $this->profitPoint -= 0.2;
                                break;
                            }
                        } elseif ($afterUser['sg_level'] == 3) {
                            // 收益者来源星级 == 3，跳出
                            $this->profitPoint -= 0.3;
                            break;
                        }

                        $profit   = sprintf("%.4f", ($val['t_profit'] * $rate));;

                        $insert[] = [
                            'members_id'        => $beforUser['id'],       // 社群收益者 ID
                            't_level'           => $beforUser['sg_level'], // 社群收益者 星级
                            't_profit'          => $profit,                // 社群收益 结算
                            't_rate'            => $rate,                  // 社群收益 结算比率
                            'from_members_id'   => $val['members_id'],     // 社群收益来源者 ID
                            'from_profit'       => $val['t_profit'],       // 社群收益来源 基数
                            'from_level'        => $afterUser['sg_level']  // 社群收益来源者 星级
                        ];

                        $this->sgLevelTotal[$beforUser['sg_level']] += 1;
                        break;
                    case 3:
                        if ($this->sgLevelTotal[3] > 1)
                        {
                            // >1 是因为 3 星等级最多只能有两位 3 星级用户可以获取社群收益
                            break;
                        } elseif ($this->sgLevelTotal[1] > 0 && $this->sgLevelTotal[2] == 0 && $this->sgLevelTotal[3] == 0) {
                            // 1 星进行社群收益计算，2 星没有

                            $rate = 0.2;
                            $this->profitPoint -= 0.2;

                        } elseif ($this->sgLevelTotal[1] == 0 && $this->sgLevelTotal[2] > 0 && $this->sgLevelTotal[3] == 0) {
                            // 1 星没进行社群收益计算，2 星有

                            $rate = 0.1;
                            $this->profitPoint -= 0.1;

                        } elseif ($this->sgLevelTotal[1] > 0 && $this->sgLevelTotal[2] > 0 && $this->sgLevelTotal[3] == 0) {
                            // 1、2 星都进行了社群收益计算

                            $rate = 0.1;
                            $this->profitPoint -= 0.1;

                        } elseif ($afterUser['sg_level'] == $beforUser['sg_level'] && $this->sgLevelTotal[3] == 0){
                            // 三星平级，只能获取 10% 的收益。

                            $rate = 0.1;
                            $this->profitPoint -= 0.4;
                            $this->sgLevelTotal[3] += 1;

                        } elseif ($this->sgLevelTotal[1] == 0 && $this->sgLevelTotal[2] == 0 && $this->sgLevelTotal[3] == 0) {
                            // 1、2、3 星都没有进行收益计算
                            if ($afterUser['sg_level'] == 0)
                            {
                                $rate = 0.3;
                                $this->profitPoint -= 0.3;
                            } elseif ($afterUser['sg_level'] == 1) {
                                $rate = 0.2;
                                $this->profitPoint -= 0.3;
                            } elseif ($afterUser['sg_level'] == 2) {
                                $rate = 0.1;
                                $this->profitPoint -= 0.3;
                            }

                        } elseif ($this->sgLevelTotal[3] > 0) {
                            // 已经计算了一次 3 星等级的社区收益了，代码运行在此说明出现了 3 星平级，
                            // 此次的收益将是 10%

                            $rate = 0.1;
                            $this->profitPoint -= 0.1;
                        }

                        $profit   = sprintf("%.4f", ($val['t_profit'] * $rate));;

                        $insert[] = [
                            'members_id'        => $beforUser['id'],       // 社群收益者 ID
                            't_level'           => $beforUser['sg_level'], // 社群收益者 星级
                            't_profit'          => $profit,                // 社群收益 结算
                            't_rate'            => $rate,                  // 社群收益 结算比率
                            'from_members_id'   => $val['members_id'],     // 社群收益来源者 ID
                            'from_profit'       => $val['t_profit'],       // 社群收益来源 基数
                            'from_level'        => $afterUser['sg_level']  // 社群收益来源者 星级
                        ];

                        $this->sgLevelTotal[3] += 1;
                        break;
                }
            }

            // 初始化统计数据
            $this->sgLevelTotal = [
                1 => 0,
                2 => 0,
                3 => 0
            ];
            $this->profitPoint  = 0.4;

        }// foreach ($res as $val) end

        //help_p('打印 insert[]');
        //help_p($insert);

        if (empty($insert))
            return null;

        //return null;
        Db::startTrans();// 事务开始
        try {
            $TaskLogs->addLogs('start');

            $ProfitLogs = new ProfitLogs();
            // 社群收益用户记录-写入
            $ProfitLogs->addSocialGroup($insert);

            // 社群收益结算写入 jm_members.sg_profit 里
            foreach ($insert as $val3) {
                //$val3['members_id'];
                //$val3['t_profit'];
                $Members->addSgProfit($val3['members_id'], $val3['t_profit']);
            }

            $TaskLogs->addLogs('end');
            Db::commit();// 事务提交
        } catch (\Exception $e) {
            Db::rollback();// 事务回滚

            help_test_logs(['社群收益计算出现错误', '原因：'.$e->getMessage()]);
            $TaskLogs->addLogs('err');
        }
    }// task() end

}// SocialGroupsController{} end