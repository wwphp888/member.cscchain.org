<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/19
 * Time: 14:07
 */

namespace api\common\model;

use think\Db;
use think\Model;

class MedalModel extends Model
{
    /** 勋章进资金
     * @param $mid 用户ID
     * @param $money 金额
     * @return bool
     */
    public function medalChange($mid, $to_mid, $medal, $type = 1, $msg = '')
    {
        if (empty($mid)) {
            return false;
        }

        try {
            $info = self::where(['mid' => $mid])->lock(true)->find();

            if (empty($info))
            {
                $data = [
                    'mid'           => $mid,
                    'medal'         => $medal,
                    'medal_money'   => 0,
                    'create_time'   => time(),
                    'update_time'   => time(),
                ];

                if (Db::name('medal')->insert($data) == false)
                {
                    return false;
                }
            } else {
                if (($info['medal'] + $medal) < 0)
                {
                    return false;
                }

                $data = [
                    'medal'         => $info['medal'] + $medal,
                    'update_time'   => time(),
                ];

                if (Db::name('medal')->where(['mid' => $mid])->update($data) == false) {
                    return false;
                }

            }
            //记录日志
            $model = new MedalLogModel();
            if ($model->log($mid, $to_mid, $medal, $type, $msg) === false)
            {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    //添加勋章金额
    public function addMedalMoney($mid, $money)
    {
        $info = self::where(['mid' => $mid])->field("mid,medal,medal_money")->find();

        if (empty($info)) {
            $oldmoney   = $money;
            $medal      = floor($money / 20000);

            if ($medal > 0)
            {
                $money = $money % 20000;
            }

            $data = [
                'mid'           => $mid,
                'medal'         => $medal,
                'medal_money'   => $money,
                'create_time'   => time(),
                'update_time'   => time(),
            ];

            if (Db::name('medal')->insert($data) == false)
            {
                return false;
            }

            if ($medal > 0) {
                //记录日志
                $model  = new MedalLogModel();
                $msg    = $oldmoney . "流水达到添加勋章" . $medal;
                if ($model->log($mid, 0, $medal, 2, $msg) === false)
                {
                    return false;
                }
            }

            return true;
        } else {
            $totalMoney = $info['medal_money'] + $money;
            $medal      = floor($totalMoney / 20000);

            if ($medal > 0) {
                $totalMoney = $totalMoney % 20000;
            }
            $data = [
                'medal_money'   => $totalMoney,
                'medal'         => $info['medal'] + $medal,
            ];
            if (Db::name('medal')->where(['mid' => $mid])->update($data) == false) {
                return false;
            }
            if ($medal > 0) {
                //记录日志
                $model = new MedalLogModel();
                $msg = $info['medal_money'] + $money . "流水达到添加勋章" . $medal;
                if ($model->log($mid, 0, $medal, 2, $msg) === false) {
                    return false;
                }
            }
            return true;
        }
    }


    /**  获取资产
     * @param $mid
     * @param string $table
     * @return array|mixed|null|\PDOStatement|string|Model
     */
    public function getMedal($mid, $table = "*")
    {
        $info = self::where(['mid' => $mid])->field("mid,medal,medal_money,medal_time")->find();
        if (empty($info)) {
            $data = [
                'mid'           => $mid,
                'medal'         => 0,
                'medal_money'   => 0,
                'create_time'   => time(),
                'update_time'   => time(),
            ];

            if (Db::name('medal')->insert($data) == false) {
                return 0;
            }
            $info = $data;
        }
        if ($table === '*') {
            return $info;
        }
        return $info[$table];
    }
}