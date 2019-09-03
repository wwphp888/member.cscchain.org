<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;

use app\common\controller\AdminBaseController;
use service\Block;
use think\Db;
use app\admin\model\AdminMenuModel;
use app\admin\model\StaticRevenueInterestLogsModel as StaticRevenue;
use app\admin\model\DynamicRevenueInterestLogsModel as DynamicRevenue;
use app\admin\model\SocialGroupProfitLogsModel as SocialGroupProfit;

class IndexController extends AdminBaseController
{

    public function initialize()
    {
        $adminSettings = cmf_get_option('admin_settings');
        if (empty($adminSettings['admin_password']) || $this->request->path() == $adminSettings['admin_password']) {
            $adminId = cmf_get_current_admin_id();
            if (empty($adminId)) {
                session("__LOGIN_BY_CMF_ADMIN_PW__", 1);//设置后台登录加密码
            }
        }

        parent::initialize();
    }

    /**
     * 后台首页
     */
    public function index()
    {
        $content = hook_one('admin_index_index_view');

        if (!empty($content)) {
            return $content;
        }

        $adminMenuModel = new AdminMenuModel();
        $menus = cache('admin_menus_' . cmf_get_current_admin_id(), '', null, 'admin_menus');

        if (empty($menus)) {
            $menus = $adminMenuModel->menuTree();
            cache('admin_menus_' . cmf_get_current_admin_id(), $menus, null, 'admin_menus');
        }

        $this->assign("menus", $menus);


        $result = Db::name('AdminMenu')->order(["app" => "ASC", "controller" => "ASC", "action" => "ASC"])->select();

        $menusTmp = array();
        foreach ($result as $item) {
            //去掉/ _ 全部小写。作为索引。
            $indexTmp = $item['app'] . $item['controller'] . $item['action'];
            $indexTmp = preg_replace("/[\\/|_]/", "", $indexTmp);
            $indexTmp = strtolower($indexTmp);
            $menusTmp[$indexTmp] = $item;
        }
        $this->assign("menus_js_var", json_encode($menusTmp));

        return $this->fetch();
    }

    // 统计中心
    public function main(
        StaticRevenue       $StaticRevenue,
        DynamicRevenue      $DynamicRevenue,
        SocialGroupProfit   $SocialGroupProfit
    ) {
        //获取上一期的数据
        $data = [
            'members'       => 0,
            'todayMembers'  => 0,
        ];

        $data['members'] = dbMember("members")
                            ->where("project", 'zing')
                            ->field("id")
                            ->count();

        $time = strtotime(date("Y-m-d"));
        $data['todayMembers'] = dbMember("members")
                                    ->where("project", 'zing')
                                    ->where("create_time", 'gt', $time)
                                    ->field("id")
                                    ->count();


        $data['static_revenue']      = $StaticRevenue->all2();    // 获取当天所有的静态收益
        $data['dynamic_revenue']     = $DynamicRevenue->all();    // 获取当天所有的动态收益
        $data['social_group_profit'] = $SocialGroupProfit->all(); // 获取当天所有的社群收益

        $this->assign($data);
        return $this->fetch();
    }// main() end
}
