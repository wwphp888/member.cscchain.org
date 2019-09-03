<?php
/*
 * 动态收益
 * 动态收益需要在静态收益执行完毕后再执行，
 * 因为动态收益需要用到 jm_static_revenue_interest_logs[静态收益用户记录表] 的数据
 * 作者：ck
 * */

namespace app\admin\controller;

use think\Db;
use think\facade\Env;
use app\common\controller\AdminBaseController;
use app\admin\model\MembersModel as Members;
use app\admin\model\DynamicRevenueTaskLogsModel as DynamicLogs;
use app\admin\model\StaticRevenueInterestLogsModel as InterestLogs;
use app\admin\model\DynamicRevenueInterestLogsModel as Dynamic;

class DynamicRevenueController extends AdminBaseController
{
    protected $taskLogs = null; // 动态收益定时任务执行记录表对象
    protected $uids     = [8];  // 黑名单用户 ID

    public function __construct()
    {
        $this->taskLogs = new DynamicLogs();
    }// __construct()

    public function test()
    {

    }// test() end

    public function task()
    {
        if ($this->taskLogs->today()) {
            $this->taskLogs->addLogs('stop');
            return null;
        }

        set_time_limit(0);
        $this->taskLogs->addLogs('start');

        $Members        = new Members();
        $InterestLogs   = new InterestLogs();

        // 获取当天所有静态收益
        $res    = $InterestLogs->all();
        $insert = []; // 需要写入的数据

        Db::startTrans();// 启动事务
        try{
            foreach ($res as $val) {
                //$val['members_id'];// jm_members.id
                //$val['t_profit'];// 当天静态收益

                // 获取用户的所有上级会员
                $res2 = $Members->beforUsers($val['members_id'], ['is_dis_award' => 0, 'is_disabled' => 0]);

                foreach ($res2 as $key => $val2) {
                    // 当前的代数,因为 $key 初始值为 0，所以需要 +1
                    $algebra = $key + 1;

                    // 跳出黑名单用户
                    if (in_array($val2, $this->uids))
                        continue;

                    // 获取当前推荐人累计推荐人数
                    $totalPeople = $Members->pushBeforTotal($val2, ['is_dis_award' => 0, 'is_disabled' => 0]);

                    // 推荐人数不达要求，不给于动态奖励
                    if ($totalPeople < 1) {
                        continue;
                    }

                    // 动态收益- 收益级别计算
                    $level = $this->incomeClassification($totalPeople, $algebra);
                    $level = $level > 0 ? $level : 0;// 防止 php 将 0 隐式转换成 null。

                    // 动态收益算法[获取当前用户动态收益值]
                    $dynamic = $this->revenueAlgorithm($level, $val['t_profit']);

                    // 将动态收益 >0 的写入表内
                    if ($dynamic > 0) {
                        $success = $Members->addDynamic($val2, $dynamic);

                        if ($success > 0)
                        {
                            // 需要写入数据库的数据
                            $insert[] = [
                                'members_id'        => $val2,
                                't_dynamic'         => $dynamic,
                                'algebra'           => $level,
                                'from_members_id'   => $val['members_id'],
                                'from_profit'       => $val['t_profit']
                            ];
                        }
                    }
                }
            }
            //help_p($insert);

            // 写入单个用户的动态收益
            $Dynamic = new Dynamic();
            $Dynamic->addDynamicRevenue($insert);

            Db::commit();// 提交事务
        } catch (\Exception $e) {
            //help_p($e->getMessage());

            Db::rollback();// 回滚事务
            help_test_logs(['动态收益任务错误', $e->getMessage()]);

            // 动态收益任务-错误【将错误进行记录】
            $this->taskLogs->addLogs('err');
        }

        $this->taskLogs->addLogs('end');
    }// task() end

    /*
     * 动态收益算法
     * 参数1：int   $level  收益级别
     * 参数2：float $profit 静态收益
     * return float
     * */
    public function revenueAlgorithm(int $level = 0, float $profit = 0.0000)
    {
        /*
         * 直推 1 人：拿 1 代     100%
         * 直推 2 人：拿 2 代     50%
         * 直推 3 人：拿 3 代     30%
         * 直推 4 人：拿 4-9 代   10%
         * 直推 5 人：拿 10-20 代 5%
         * */

        if ($level > 5 && is_int($level)) {
            $people = 5;
        }

        switch ($level) {
            case 1:
                $profit = $profit * 1;
                break;
            case 2:
                $profit = $profit * 0.5;
                break;
            case 3:
                $profit = $profit * 0.3;
                break;
            case 4:
                $profit = $profit * 0.1;
                break;
            case 5:
                $profit = $profit * 0.05;
                break;
            default:
                $profit = 0;
        }

        return sprintf("%.4f", $profit);
    }// revenueAlgorithm() end

    /*
     * 动态收益- 收益级别计算
     * 参数1：int $totalPeople 推荐人数
     * 参数2：int $algebra     代数
     * return int
     * */
    public function incomeClassification(int $totalPeople = 0, int $algebra = 0)
    {
        if ($algebra === 1 && $totalPeople >= 1) {
            // 1 代收益等级转换
            return 1;
        } else if ($algebra === 2 && $totalPeople >= 2) {
            // 2 代收益等级转换
            return 2;
        } else if ($algebra === 3 && $totalPeople >= 3) {
            // // 3 代收益等级转换
            return 3;
        } else if ($algebra >= 4 && $algebra <= 9 && $totalPeople >= 4) {
            // // 4-9 代收益等级转换
            return 4;
        } else if ($algebra >= 10 && $algebra <= 20 && $totalPeople >= 5) {
            //// 10-20 代收益等级转换
            return 5;
        } else {
            return 0;
        }
    }// incomeClassification() end

}// DynamicRevenueController{} end