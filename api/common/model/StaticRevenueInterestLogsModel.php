<?php
/*
 * jm_static_revenue_interest_logs 静态收益用户记录表
 * 作者：ck
 * */

namespace api\common\model;

use think\Model;

class StaticRevenueInterestLogsModel extends Model
{
    protected $table = 'jm_static_revenue_interest_logs';
    protected $pk    = 'id';

    /*
     * 写入单个用户的静态收益
     * 参数1：array $insert 待写入的数据集
     * */
    public function addStaticRevenue(array $insert = [])
    {
        // 数据集的格式
        /*$insert[] = [
            'members_id' => $val['id'],
            't_profit'   => $interest
        ];*/

        if (empty($insert) === false) {
            $this->table($this->table)->insertAll($insert);
        }
    }// addStaticRevenue() end

    /*
     * 获取当天所有的静态收益的数据
     * return array
     * */
    public function all()
    {
        $res = $this->table($this->table)
                ->where('create_time', '>=', date('Y-m-d 00:00:00'))
                ->field('members_id, t_profit')
                ->select();

        return $res;
    }// all() end

    /*
     * 获取当天所有的静态收益的总和
     * return int
     * */
    public function all2(string $field = 't_profit')
    {
        $res = $this->table($this->table)
                    ->where('create_time', '>=', date('Y-m-d 00:00:00'))
                    ->sum($field);

        return $res;
    }// all() end

    /*
     * 获取单用户静态收益
     * 参数1：int    $tid  表id
     * */
    public function one(int $tid = 0)
    {
        if ($tid < 1 || is_int($tid) === false) {
            return 0;
        }

        $res = $this->table($this->table)
                ->where('members_id', $tid)
                ->where('create_time', '>=', date('Y-m-d 00:00:00'))
                ->field('t_profit')
                ->find();
        //echo $this->table($this->table)->getLastSql();
        //help_p($res);

        if (empty($res)) {
            return 0;
        } else {
            return $res['t_profit'];
        }
    }// getProfit() end
}// StaticRevenueInterestLogs{} end