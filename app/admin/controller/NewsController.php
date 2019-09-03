<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/14
 * Time: 21:01
 */

namespace app\admin\controller;

use app\common\controller\AdminBaseController;

class NewsController extends AdminBaseController
{
    public function broadcast()
    {
        if (request()->isPost()) {
            $data = input();
            $data['update_time']=time();
            if (db("news")->where("id", 1)->update($data)) {
                return $this->success("更新成功");
            } else {
                return $this->success("更新失败");
            }

        }
        $data = db("news")->where("id", 1)->find();

        $this->assign($data);
        return $this->fetch();
    }

    public function rule()
    {
        if (request()->isPost()) {
            $data = input();
            $data['update_time']=time();
            if (db("news")->where("id", 2)->update($data)) {
                return $this->success("更新成功");
            } else {
                return $this->success("更新失败");
            }

        }
        $data = db("news")->where("id", 2)->find();

        $this->assign($data);
        return $this->fetch();
    }
}