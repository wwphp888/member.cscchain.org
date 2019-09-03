<?php
/*
 * jm_static_revenue_task_logs 静态收益定时任务执行记录表模型
 * 作者ck
 * */

namespace app\admin\model;

use think\Model;

class StaticRevenueTaskLogsModel extends Model
{
    protected $table = 'jm_static_revenue_task_logs';
    protected $pk    = 'id';

    /*
     * 写入执行任务记录
     * 参数1：string $t_status 动作
     * return null
     * */
    public function addLogs(string $t_status = '')
    {
        switch($t_status){
            case 'start':
                $this->table($this->table)->insert([
                    't_status' => 'start'
                ]);
                break;
            case 'end':
                $this->table($this->table)->insert([
                    't_status' => 'end'
                ]);
                break;
            case 'err':
                $this->table($this->table)->insert([
                    't_status' => 'err'
                ]);
                break;
            case 'stop':
                $this->table($this->table)->insert([
                    't_status' => 'stop'
                ]);
                break;
        }

        return false;
    }// addLogs() end

    /*
     * 获取今日执行任务记录
     * return boolean
     * */
    public function today()
    {
        $res = $this->table($this->table)
                ->where('create_time', '>=', date('Y-m-d 00:00:00'))
                ->where('t_status', 'end')
                ->find();
        //echo $this->table($this->table)->getLastSql();
        //help_p($res);

        return !empty($res);
    }// today() end
}// StaticRevenueTaskLogsModel{} end