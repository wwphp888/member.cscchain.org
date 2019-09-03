<?php
/*
 * 社群收益用户记录表
 * 作者：ck
 * */

namespace app\admin\model;

use think\Model;

class SocialGroupProfitLogsModel extends Model
{
    protected $table = 'jm_social_group_profit_logs';
    protected $pk    = 'id';

    /*
     * 写入单个用户的社群收益
     * 参数1：array $insert 待写入的数据集
     * */
    public function addSocialGroup(array $insert = [])
    {
        // 数据集的格式
        /*$insert[] = [
            'members_id'        => $val2,             // 社群收益者 ID
            't_profit'          => $profit,           // 社群收益结算
            'from_members_id'   => $val['members_id'],// 社群收益来源者 ID
            'from_profit'       => $val['t_profit'],  // 社群收益基数
            't_level'           => $sgLevel           // 收益者社群星级
        ];*/

        if (empty($insert) === false) {
            $this->table($this->table)->insertAll($insert);
        }
    }// addSocialGroup() end

    /*
     * 获取用户当天的社群收益总和
     * 参数1：array $where 条件
     * */
    public function SocialGroupProfitOne(array $where = [])
    {
        $sum = $this->table($this->table)
                ->where($where)
                ->sum('t_profit');

        return $sum;
    }// SocialGroupProfitOne() end

    /*
     * 获取当天所有的社群收益总和
     * return int
     * */
    public function all(string $field = 't_profit')
    {
        $res = $this->table($this->table)
            ->where('create_time', '>=', date('Y-m-d 00:00:00'))
            ->sum($field);

        return $res;
    }// all() end

}// SocialGroupProfitLogsModel{} end