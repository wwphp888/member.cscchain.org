<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/22
 * Time: 19:39
 */

namespace api\common\model;

use think\Model;

class LockedModel extends Model
{
    public function getLocked($mid)
    {
        return self::where("mid", $mid)
                ->where("status", 0)
                ->cache(0)
                ->sum("money");
    }
}