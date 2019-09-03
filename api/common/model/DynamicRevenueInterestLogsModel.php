<?php
/*
 * jm_dynamic_revenue_interest_logs 动态收益用户记录表
 * 作者：ck
 * */

namespace api\common\model;

use think\Model;
use think\Db;

class DynamicRevenueInterestLogsModel extends Model
{
    protected $table = 'jm_dynamic_revenue_interest_logs';
    protected $pk    = 'id';

    /*
     * 写入单个用户的动态收益
     * 参数1：array $insert 待写入的数据集
     * */
    public function addDynamicRevenue(array $insert = [])
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
     * 获取用户当天静态收益总和
     * 参数1：array $where 条件
     * */
    public function dynamicRevenueOne(array $where = []) {
        $sum = $this->table($this->table)
                ->where($where)
                ->sum('t_dynamic');

        return $sum;
    }// dynamicRevenueOne() end

    /*
     * 获取当天所有的动态收益总和
     * return int
     * */
    public function all(string $field = 't_dynamic')
    {
        $res = $this->table($this->table)
            ->where('create_time', '>=', date('Y-m-d 00:00:00'))
            ->sum($field);

        return $res;
    }// all() end

}// DynamicRevenueInterestLogs{} end