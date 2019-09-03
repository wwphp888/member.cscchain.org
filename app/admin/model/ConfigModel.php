<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 10:48
 */

namespace app\admin\model;

use think\Model;

class ConfigModel extends Model
{
    public function saveConfig($data)
    {
        if (empty($data) || !is_array($data)) {
            $this->error = "数据不能为空";
            return false;
        }
        //循环更新数据
        foreach ($data as $key => $vo) {
            if (empty($key)) {
                continue;
            }
            $save = array();
            $save["value"] = trim($vo);
            if (db('config')->where(['varname' => $key])->update($save) === false) {
                $this->error = "更新到{$key}项时，更新失败！";
                return false;
            }
        }
        $this->get_cache();
        return true;
    }

    //获取自定义数据
    public function get_config($vargroup)
    {
        return self::where(["vargroup" => $vargroup, "issystem" => 0])->select();
    }

    public function get_cache()
    {
        $data = db('config')->column("varname,value");
        redis()->set("config", $data);
        return $data;
    }
}