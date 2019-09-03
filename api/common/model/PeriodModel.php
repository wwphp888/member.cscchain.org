<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/19
 * Time: 11:06
 */

namespace api\common\model;

use think\Db;
use think\Model;

class PeriodModel extends Model
{

    /**
     * 获取上一期
     */
    public function getPrevious()
    {

        //$time = strtotime(date('Ymd 09:00:00'));
        //$endtime = strtotime(date('Ymd 10:02:00'));
        $time       = strtotime(date('Ymd 10:17:00'));
        $endtime    = strtotime(date('Ymd 10:23:00'));

        $data = self::where("status", 1)
                ->order("id desc")
                ->field("btc_usdt,lottery_num,advanced,lottery_time,destroy")
                ->find()
                ->toArray();

        $data['lottery_time']       = date("d.m.Y h:i:a", $data['lottery_time']);
        $data["todayDestruction"]   = $data['destroy'];

        $new_data = self::where(1)->order("id desc")->find();
        unset($data['destroy']);

        if ((time() > $time && $endtime > time()) ||
            $new_data['status'] == 2
        ) {
            $data["period"] = [
                'btc_usdt'      => "?",
                'lottery_num'   => "?",
                'advanced'      => "?",
                'lottery_time'  => "?",
            ];
        } else {
            $data["period"] = $data;
        }
        return $data;
    }

    /**
     * 获取本期，0 和 2 的
     */
    public function getThisPeriod()
    {
        return self::where("status", "in", "0,2")->value("period_no");
    }
}