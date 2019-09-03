<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/18
 * Time: 16:41
 */

namespace api\common\model;

use service\Block;
use think\Model;

class RegionModel extends Model
{
    /**
     *
     */
    public function getTotalAmount()
    {
        $key    = getCacheKey('Region:getTotalAmount');
        $data   = cache($key);

        if (empty($data)) {
            $block  = new Block();
            $region = getRegion();
            $data   = [];
            $i      = 0;

            foreach ($region as $v) {
//                help_p('打印 address - start');
//                help_p($v['address']);
//                help_p('打印 address - end');

                //获取账户余额
                $info = $block->get_token($v['address'], 1);

                if ($info['balance'] > 0)
                {
                    $balance = $info['balance'];
                } else {
                    $balance = 0;
                }

                $data[$i]["id"]         = $v['id'];
                $data[$i]["balance"]    = $balance;
                $hit                    = str_replace(",", '&', $v['hit']);
                $data[$i]["hit"]        = "[{$hit}]";
                $data[$i]["name"]       = $v['name'];
                $data[$i]["enname"]     = str_replace(" ", "\n", $v['enname']);

                $i++;
            }

            cache($key, $data, 10);
        }

        return $data;
    }
}