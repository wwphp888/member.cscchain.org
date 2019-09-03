<?php
/**
 * Time: 17:31
 */

namespace app\admin\controller;


use app\common\controller\AdminBaseController;
use think\Db;

class RegistrationController extends AdminBaseController
{
    public function index($page=1)
    {
        $search =[];
        if (input('search') == 1) {
            $search = input();
            $this->assign('search', $search);
        }
        if(empty($search['key'])){
            $search['key'] = '';
        }
        $list =  Db::name("registration")->where("phone", 'like', "%{$search['key']}%")->order('create_time desc')->paginate(15);
        foreach ($list as $key => &$vo) {
            $vo['create_time'] = date("Y-m-d H:i:s",$vo['create_time']);
        }

        $this->assign('lists', $list);
        $this->assign('page', $list->render());
        return $this->fetch();
    }
}