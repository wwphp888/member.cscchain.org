<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/24
 * Time: 17:31
 */

namespace app\admin\controller;

use app\common\controller\AdminBaseController;
use think\Db;
use app\admin\model\SunTraccountModel as SunTraccount;
use app\admin\model\MembersModel as Members;
use think\Request;
use app\admin\model\StaticRevenueInterestLogsModel as StaticRevenue;
use app\admin\model\DynamicRevenueInterestLogsModel as DynamicRevenue;
use app\admin\model\SocialGroupProfitLogsModel as SocialGroupProfit;

class BookController extends AdminBaseController
{
    /*
     * 转账记录
     * */
    public function transfer(SunTraccount $SunTraccount, Members $Members, Request $request)
    {
        $where          = [];
        $mobile         = trim($request->get('mobile'));      // 手机号
        $address        = trim($request->get('address'));     // 钱包地址
        $update_time    = trim($request->get('update_time')); // 到账时间
        $status         = trim($request->get('status'));      // 交易状态

        if (strlen($mobile) > 20)
            return $this->error(lang('error').'-1');

        if (empty($mobile) === false && is_numeric($mobile))
        {
            $user = $Members->one('id', ['mobile' => $mobile]);
            if (empty($user))
            {
                return $this->error(lang('error').'-2');
            } else {
                $where[] = ['mid', '=', $user['id']];
            }
        }

        if (strlen($address) > 20)
            return $this->error(lang('error').'-3');

        if (empty($address) === false && is_string($address))
            $where[] = ['address', '=', $address];

        if (strlen($update_time) > 10)
            return $this->error(lang('error').'-4');

        if (empty($update_time) === false && is_string($update_time))
            $where[] = ['update_time', '>=', strtotime($update_time)];

        if (in_array($status, ['1', '2']))
            $where[] = ['status', '=', $status];

        if (empty($where))
        {
            // 非搜索流水
            $res = $SunTraccount
                ->field('ordersn, mid, true_money, address, remark, status, type, update_time')
                ->order('id', 'desc')
                ->paginate(15);
        } else {
            // 搜索流水，不做分页显示
            $count = $SunTraccount->where($where)->count('id');

            $res = $SunTraccount
                    ->where($where)
                    ->field('ordersn, mid, true_money, address, remark, status, type, update_time')
                    ->order('id', 'desc')
                    ->paginate($count);
        }

        // 优化部分字段显示
        foreach ($res as $key => $val) {
            $res[$key]['update_time'] = date('Y-m-d H:i:s', $val['update_time']);

            $payment = $Members
                        ->field('mobile, address')
                        ->where('id', $val['mid'])
                        ->find();

            $collection = $Members
                            ->field('mobile, address')
                            ->where('address', $val['address'])
                            ->find();

            $res[$key]['status'] = $val['status'] == 1 ? '成功' : '失败'; // 成功状态

            if ($val['type'] === 1)
            {
                // 转出
                $res[$key]['type'] = '转出';

                $res[$key]['mobile']  = $payment['mobile'];        // 转出-手机号
                $res[$key]['address'] = $payment['address'];       // 转出-地址

                $res[$key]['to_mobile']  = $collection['mobile'];  // 转入-手机号
                $res[$key]['to_address'] = $collection['address']; // 转入-地址
            } else {
                // 转入
                $res[$key]['type']       = '转入';

                $res[$key]['mobile']     = $collection['mobile'];  // 转出-手机号
                $res[$key]['address']    = $collection['address']; // 转出-地址

                $res[$key]['to_mobile']  = $payment['mobile'];     // 转入-手机号
                $res[$key]['to_address'] = $payment['address'];    // 转入-地址
            }

        }

        $this->assign('list', $res);

        return $this->fetch();
    }// transfer() end

    /*
     * 静态收益流水
     * */
    public function staticRevenue(StaticRevenue $StaticRevenue, Request $request, Members $Members)
    {
        $where          = [];
        $mobile         = $request->get('mobile');      // 手机号
        $create_time    = $request->get('create_time'); // 时间

        if (empty($mobile) === false && is_numeric($mobile))
        {
            if (strlen($mobile) > 20)
                return $this->error(lang('error').'-1');

            // 获取 jm_members.id 作为查询条件
            $user = $Members->one('id', ['mobile' => $mobile]);
            if (empty($user))
                return $this->error(lang('error').'-2');

            $where[] = ['members_id', '=', $user['id']];
        }

        if (empty($create_time) === false && strlen($create_time) === 10)
            $where[] = ["create_time", '>=', $create_time];

        if (empty($where))
        {
            $res = $StaticRevenue
                ->alias('a')
                ->join('jm_members b', 'a.members_id=b.id')
                ->field('a.t_profit, a.create_time, b.number, b.mobile')
                ->order('a.id', 'desc')
                ->paginate(15);
        } else {
            // 有搜索条件将不进行分页显示
            $count = $StaticRevenue->where($where)->count('id');

            if (empty($mobile) === false && empty($create_time) === false)
            {
                $res = $StaticRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_profit, a.create_time, b.number, b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($count);
            } elseif (empty($mobile))
            {
                $res = $StaticRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_profit, a.create_time, b.number, b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->order('a.id', 'desc')
                    ->paginate($count);
            } elseif (empty($create_time)) {
                $res = $StaticRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_profit, a.create_time, b.number, b.mobile')
                    ->where($where)
                    ->order('a.id', 'desc')
                    ->paginate($count);
            }
        }

        $this->assign('list', $res);

        return $this->fetch();
    }// staticRevenue() end

    /*
     * 动态收益流水
     * */
    public function dynamicRevenue(DynamicRevenue $DynamicRevenue, Request $request, Members $Members)
    {
        $where          = [];
        $mobile         = $request->get('mobile');      // 手机号
        $create_time    = $request->get('create_time'); // 时间

        if (empty($mobile) === false && is_numeric($mobile))
        {
            if (strlen($mobile) > 20)
                return $this->error(lang('error').'-1');

            $user = $Members->where('mobile', $mobile)->find();
            if (empty($user))
                return $this->error(lang('error').'-2');

            $where[] = ['members_id' , '=', $user['id']];
        }

        if (empty($create_time) === false && strlen($create_time) === 10)
            $where[] = ['create_time', '>=', $create_time];

        if (empty($where))
        {
            $res = $DynamicRevenue
                ->alias('a')
                ->join('jm_members b', 'a.members_id=b.id')
                ->field('a.t_dynamic, a.from_profit, a.algebra, a.from_members_id, a.create_time, b.mobile')
                ->order('a.id', 'desc')
                ->paginate(15);

            $res2 = $DynamicRevenue
                ->alias('a')
                ->join('jm_members b', 'a.from_members_id=b.id')
                ->field('b.mobile')
                ->order('a.id', 'desc')
                ->paginate(15);
        } else {
            $limit = 1000000;
            if (empty($mobile) === false && empty($create_time) === false)
            {
                $res = $DynamicRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_dynamic, a.from_profit, a.algebra, a.from_members_id, a.create_time, b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);

                $res2 = $DynamicRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.from_members_id=b.id')
                    ->field('b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);
            } elseif (empty($mobile) === false) {

                $res = $DynamicRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_dynamic, a.from_profit, a.algebra, a.from_members_id, a.create_time, b.mobile')
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);

                $res2 = $DynamicRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.from_members_id=b.id')
                    ->field('b.mobile')
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);
            } elseif (empty($create_time) === false) {
                $res = $DynamicRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_dynamic, a.from_profit, a.algebra, a.from_members_id, a.create_time, b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->order('a.id', 'desc')
                    ->paginate($limit);

                $res2 = $DynamicRevenue
                    ->alias('a')
                    ->join('jm_members b', 'a.from_members_id=b.id')
                    ->field('b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->order('a.id', 'desc')
                    ->paginate($limit);
            }
        }

        foreach ($res as $key => $val) {
            $res[$key]['from_mobile'] = $res2[$key]['mobile'];
        }

        $this->assign('list', $res);

        return $this->fetch();
    }// dynamicRevenue() end

    /*
     * 社群收益流水
     * */
    public function socialGroupsRevenue(SocialGroupProfit $SocialGroupProfit, Request $request, Members $Members)
    {
        $where          = [];
        $mobile         = $request->get('mobile');      // 手机号
        $create_time    = $request->get('create_time'); // 时间

        if (empty($mobile) === false && is_numeric($mobile))
        {
            if (strlen($mobile) > 20)
                return $this->error(lang('error').'-1');

            $user = $Members->where('mobile', $mobile)->find();
            if (empty($user))
                return $this->error(lang('error').'-2');

            $where[] = ['members_id' , '=', $user['id']];
        }

        if (empty($create_time) === false && strlen($create_time) === 10)
            $where[] = ['create_time', '>=', $create_time];

        if (empty($where))
        {
            $res = $SocialGroupProfit
                ->alias('a')
                ->join('jm_members b', 'a.members_id=b.id')
                ->field('a.t_level, a.t_profit, a.t_rate, a.from_profit, a.from_level, a.create_time, b.mobile')
                ->order('a.id', 'desc')
                ->paginate(15);

            $res2 = $SocialGroupProfit
                ->alias('a')
                ->join('jm_members b', 'a.from_members_id=b.id')
                ->field('b.mobile')
                ->order('a.id', 'desc')
                ->paginate(15);
        } else {
            $limit = 1000000;
            if (empty($mobile) === false && empty($create_time) === false)
            {
                $res = $SocialGroupProfit
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_level, a.t_profit, a.t_rate, a.from_profit, a.from_level, a.create_time, b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);

                $res2 = $SocialGroupProfit
                    ->alias('a')
                    ->join('jm_members b', 'a.from_members_id=b.id')
                    ->field('b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);
            } elseif (empty($mobile) === false) {

                $res = $SocialGroupProfit
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_level, a.t_profit, a.t_rate, a.from_profit, a.from_level, a.create_time, b.mobile')
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);

                $res2 = $SocialGroupProfit
                    ->alias('a')
                    ->join('jm_members b', 'a.from_members_id=b.id')
                    ->field('b.mobile')
                    ->where('a.members_id', '=', $user['id'])
                    ->order('a.id', 'desc')
                    ->paginate($limit);
            } elseif (empty($create_time) === false) {
                $res = $SocialGroupProfit
                    ->alias('a')
                    ->join('jm_members b', 'a.members_id=b.id')
                    ->field('a.t_level, a.t_profit, a.t_rate, a.from_profit, a.from_level, a.create_time, b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->order('a.id', 'desc')
                    ->paginate($limit);

                $res2 = $SocialGroupProfit
                    ->alias('a')
                    ->join('jm_members b', 'a.from_members_id=b.id')
                    ->field('b.mobile')
                    ->where('a.create_time', '>=', $create_time)
                    ->order('a.id', 'desc')
                    ->paginate($limit);
            }
        }

        foreach ($res as $key => $val) {
            $res[$key]['from_mobile'] = $res2[$key]['mobile'];
        }

        $this->assign('list', $res);

        return $this->fetch();
    }// socialGroupsRevenue() end

    /*
     * 勋章
     * @return mixed
     **/
    public function medal()
    {
        $medal_log =  Db::name("medal_log");
        if (input('search') == 1) {
            $search = input();
            if (!empty($search['key'])) {
                $members = dbMember("members")->where("number|mobile", 'like', "%{$search['key']}%")->field("id")->select();
                $id = [];
                foreach ($members as $vo) {
                    array_push($id, $vo['id']);
                }
                $medal_log->whereIn("mid", $id);
            }

            if (!empty($search['start_time'])) {
                $start_time = strtotime($search['start_time']);

                $medal_log->where("create_time","gt", $start_time);
            }
            if (!empty($search['end_time'])) {

                $end_time = strtotime($search['end_time']." 23:59:59");

                $medal_log->where("create_time","lt", $end_time);
            }
            $this->assign('search', $search);
        }
        $list =$medal_log->where("to_mid", "<>", 0)->order('create_time desc')->paginate(15);
        $data = [];
        foreach ($list as $key => $vo) {

            $data[$key] = $vo;
            if ($vo['type'] == 1) {
                $data[$key]['number'] = getUserInfo($vo['mid'], 'number');
                $data[$key]['tonumber'] = getUserInfo($vo['to_mid'], 'number');
            } else {
                $data[$key]['tonumber'] = getUserInfo($vo['mid'], 'number');
                $data[$key]['number'] = getUserInfo($vo['to_mid'], 'number');
            }
        }
        $this->assign('lists', $data);
        $this->assign('page', $list->render());
        return $this->fetch();
    }

    public function bet()
    {
        $betting_log =  Db::name("betting_log");
        if (input('search') == 1) {
            $search = input();
            if (!empty($search['key'])) {
                $members = dbMember("members")->where("number|mobile", 'like', "%{$search['key']}%")->field("id")->select();
                $id = [];
                foreach ($members as $vo) {
                    array_push($id, $vo['id']);
                }
                $betting_log->whereIn("mid", $id);
            }

            if (!empty($search['period_no'])) {
                $period_no=date("Ymd",strtotime($search['period_no']));
                $betting_log->where("period_no", $period_no);
            }

            $this->assign('search', $search);
        }
        $list =$betting_log->order('create_time desc')->paginate(15);
        $data = [];
        foreach ($list as $key => $vo) {

            $data[$key] = $vo;
            $data[$key]['number'] = getUserInfo($vo['mid'], 'number');
            $data[$key]['mobile'] = getUserInfo($vo['mid'], 'mobile');
            $region = getRegion($vo['region_id']);
            $data[$key]['name'] = $region['name'];
        }
        $this->assign('lists', $data);
        $this->assign('page', $list->render());
        return $this->fetch();
    }

    public function lottery()
    {

        $period =  Db::name("period");
        if (input('search') == 1) {
            $search = input();

            if (!empty($search['period_no'])) {
                $period_no=date("Ymd",strtotime($search['period_no']));
                $period->where("period_no", $period_no);
            }

            $this->assign('search', $search);
        }
        $list = $period->order('create_time desc')->where("status", 1)->paginate(15);
        $data = [];
        foreach ($list as $key => $vo) {

            $data[$key] = $vo;
            $data[$key]["total"] = $vo['as'] + $vo['eu'] + $vo['na'] + $vo['sa'] + $vo['af'];
            $region = getRegion($vo['advanced']);
            $data[$key]['name'] = $region['name'];
            $data[$key]['advancedzing'] = $vo[strtolower($region['abb'])];
            $ordinary = explode(",", $vo['ordinary']);
            $ordinarym = 0;
            foreach ($ordinary as $or) {
                if (empty($or)) {
                    continue;
                }
                $region = getRegion($or);
                $ordinarym += $vo[strtolower($region['abb'])];
            }

            $data[$key]['ordinary'] = $ordinarym;
            $base = explode(",", $vo['base']);
            $basem = 0;
            foreach ($base as $ba) {
                if (empty($or)) {
                    continue;
                }
                $region2 = getRegion($ba);

                $basem += $vo[strtolower($region2['abb'])];
            }
            $data[$key]['basezing'] = $basem;
        }
        $this->assign('lists', $data);
        $this->assign('page', $list->render());
        return $this->fetch();
    }

}