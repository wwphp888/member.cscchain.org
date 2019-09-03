<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/19
 * Time: 15:02
 */

namespace api\common\model;

use think\Db;
use think\Model;

class MedalLogModel extends Model
{
    /**
     * @param $mid
     * @param string $to_mid
     * @param $medal
     * @param int $type
     * @return bool
     */
    public function log($mid, $to_mid = '', $medal, $type = 1, $msg = '')
    {
        if (empty($mid)) {
            $this->error = "参数有误！";
            return false;
        }

        $data = [
            'mid'           => $mid,
            'type'          => $type,
            'to_mid'        => $to_mid,
            'medal'         => $medal,
            'create_time'   => time(),
            'msg'           => $msg,
        ];

        if (Db::name("medal_log")->insert($data) !== false) {
            return true;
        } else {
            return false;
        }
    }
}