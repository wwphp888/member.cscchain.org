<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/17
 * Time: 17:09
 */

namespace api\common\model;


use think\Db;

class SunTraccount
{
    /**
     * 添加gyb记录
     * @param $uid
     * @param $money
     */
    public function add_zing_log($uid, $type, $money)
    {
        $gyb_log = array(
            'mid'           => $uid,
            'type'          => $type,
            'money'         => $money,
            'create_time'   => time()
        );

        Db::name('zing_log')->insert($gyb_log);
    }

    /**
     * 手续费记录
     * @param $uid 用户id
     * @param $about_id 关联订单id
     * @param $poundage 手续费
     * @param $type 类型
     */
    public function poundage_log($mid, $about_id, $poundage, $type)
    {

        $data = array(
            "mid"           => $mid,
            'type'          => $type,
            'about_id'      => $about_id,
            'poundage'      => $poundage,
            'create_time'   => time(),
        );

        $result = Db::name('poundage_log')->insert($data);

        return true;
    }
}