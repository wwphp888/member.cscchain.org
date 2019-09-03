<?php
/*
 * jm_members 会员表模型
 * 作者：ck
 * */

namespace app\admin\model;

use think\Model;
use think\Db;
use app\admin\controller\StaticRevenueController as StaticRevenue;
use app\admin\model\TokenCurrencyModel as TokenCurrency;

class MembersModel extends Model
{
    // 当前模型的对应的完整表名
    protected $table   = 'jm_members';
    // 表主键
    protected $pk      = 'id';
    protected $balance = 13600;

    /*
     * 统计会员的总数
     * 参数1：array $where 条件
     * 返回值 int
     * */
    public function totalQuantity(array $where = [])
    {
        // is_dis_award: 1-冻结；0-解冻
        $total = $this->table($this->table)
                      ->where($where)
                      ->count('id');

        return $total;
    }// totalQuantity() end

    /*
     * 获取单个会员的数据
     * */
    public function one(string $column = '*', array $where = [])
    {
        $res = $this->table($this->table)
                    ->where($where)
                    ->field($column)
                    ->find();

        return $res;
    }// one() end

    /*
     * 获取会员现有的数据
     * 参数1：String $column   查询字段
     * 参数2：array  $where    条件数组
     * 参数3：int    $offset   偏移量
     * 参数4：int    $limit    条数
     * 返回值 array
     * */
    public function all($column = '*', $where = [], $offset = 0, $limit = 100)
    {
        $res = $this->table($this->table)
                    ->where($where)
                    ->field($column)
                    ->limit($offset, $limit)
                    ->select();

        return $res;
    }// all() end

    /*
     * 写入余链所获取的静态收益
     * 参数1：int       $tid    jm_members.id
     * 参数2：int/float $profit 静态收益
     * return null
     * */
    public function addProfit(int $tid = 0, $profit = 0)
    {
        if ($tid < 1)
        {
            return false;
        }
        if ($profit <= 0) {
            return false;
        }

        $res = $this->table($this->table)
                    ->where('id', $tid)
                    ->where('is_dis_award', 0)
                    ->where('is_disabled', 0)
                    ->setInc('profit', $profit);

        return $res;
    }// add() end

    /*
     * 写入动态收益
     * 参数1：int       $tid      jm_members.id
     * 参数2：int/float $dynamic  动态收益
     * return null
     * */
    public function addDynamic(int $tid = 0, $dynamic = 0)
    {
        if ($tid < 1)
        {
            return false;
        }
        if ($dynamic < 1) {
            return false;
        }

        $res = $this->table($this->table)
                    ->where('id', $tid)
                    ->where('is_dis_award', 0)
                    ->where('is_disabled', 0)
                    ->setInc('dynamic', $dynamic);

        return $res;
    }// add() end

    /*
     * 获取当前推荐人累计推荐人数
     * 参数1：int $tid 推荐人 jm_members.id
     * return int
     * */
    public function pushBeforTotal(int $tid = 0, array $where = [])
    {
        if ($tid < 1)
        {
            return 0;
        }

        $count = $this->name('members')
                      ->where($where)
                      ->where('pid', $tid)
                      ->count('id');

        return $count;
    }// pushBeforTotal() end

    /*
     * 获取用户的上级所有会员
     * 参数1：int $tid jm_members.id
     * return array
     * */
    public function beforUsers(int $tid = 0, array $where = [])
    {
        if ($tid < 1)
        {
            return [];
        }

        $res = $this->name('members')
                    ->where('id', $tid)
                    ->where($where)
                    ->field('id, relation')
                    ->find();

        if (empty($res))
        {
            return [];
        } else {
            // 获得后的数据格式如下[当前用户的 $tid=54]
            //$res['relation'] = '52-44-37-28-27-26-18-13-8-1-0';

            // 去除 -1-0 的字符
            $res['relation'] = str_replace('-1-0', '', $res['relation']);
            //help_p($res['relation']);

            if ($res['relation'] == '1-0') {
                return [];
            }

            // 将推荐关系由字符串格式转换成索引数组格式
            $arr = explode('-', $res['relation']);

            // 转换后成为以下格式的数组
            /*Array
            (
                [0] => 52
            )*/

            return $arr;
        }
    }// beforUsers() end

    /*
     * 获取当前用户的推荐人
     * 参数1：int $tid jm_members.id
     * return array
     * */
    public function beforUsersOne(int $tid = 0, array $where = [])
    {
        if ($tid < 1)
        {
            return [];
        }

        $res = $this->name('members')
                    ->where('pid', $tid)
                    ->where($where)
                    ->field('id, pid')
                    ->find();

        if (empty($res))
        {
            return [];
        }

        return $res;
    }// beforUsersOne() end

    /*
     * 获取用户直推总人数 >= X 的数据[该方法只是作为一个判断的依旧]
     * 参数1：int $tid    jm_members.id
     * 参数2：int $people 直推人数
     * return int
     * */
    public function pushUsersTrue(int $tid = 0, int $people = 5, int $level = 0, array $where = [])
    {
        if ($tid < 1)
        {
            return 0;
        }

        // 获取用户直推总人数
        $totalPeople = $this->table($this->table)
                            ->where('sg_level', '>=', $level)
                            ->where('pid', $tid)
                            ->where($where)
                            ->count('pid');

        if ($totalPeople >= $people) {
            return 1;
        }

        return 0;
    }// pushUsersTrue() end

    /*
     * 获取用户直推会员
     * 参数1：int    $tid   jm_members.pid
     * 参数2：string $field 查询字段
     * 参数3：array  $where 条件
     * return array
     * */
    public function pushUsers(int $pid = 0, string $field = '*', array $where = [])
    {
        if ($pid < 1)
            return [];

        $res = $this->table($this->table)
                    ->where('pid', $pid)
                    ->where($where)
                    ->field($field)
                    ->select();

        return $res;
    }// pushUsers() end

    /*
     * 获取当前用户的星级
     * 参数1：int $tid jm_members.id
     * return int
     * */
    public function sgLevel(int $tid = 0)
    {
        if ($tid < 1) {
            return 0;
        }

        $res = $this->table($this->table)
                    ->where('id', $tid)
                    ->field('id, sg_level')
                    ->find();

        if (empty($res)) {
            return $res['sg_level'];
        } else {
            return 0;
        }
    }// sgLevel() end

    /*
     * 更新当前用户的星级
     * 参数1：int $int   jm_members.id
     * 参数2：int $level jm_members.id.sg_level
     * return null
     * */
    public function updateLevel(int $tid = 0, int $level = 0)
    {
        if ($tid < 1)
        {
            return null;
        }

        $levelArr = [0, 1, 2, 3];// 允许更新的星级
        if (in_array($level, $levelArr) === false)
        {
            return null;
        }

        try{
            // 执行更新操作
            $this->table($this->table)
                 ->where('is_disabled', 0)
                 ->where('is_dis_award', 0)
                 ->where('id', $tid)
                 ->setInc('sg_level', 1);
        } catch (\Exception $e) {
            help_test_logs([
                '更新 id '.$tid.' 星群等级为 '.$level.' 失败。',
                $e->getMessage()
            ]);
        }
    }// updateLevel() end

    /*
     * 社群收益结算
     * 参数1：int   $tid       jm_members.id
     * 参数2：float $sgProfit  社群收益值
     * */
    public function addSgProfit(int $tid = 0, float $sgProfit = 0.00)
    {
        if ($tid < 1)
        {
            return null;
        }

        $this->table($this->table)
             ->where('id', $tid)
             ->where('is_disabled', 0)
             ->where('is_dis_award', 0)
             ->setInc('sg_profit', $sgProfit);
    }// addSgProfit() end

    /*
     * 会员社群星级自动升降
     * */
    public function socialGroupsLevel()
    {
        // 获取会员记录
        $res = $this->table($this->table)
                    ->where('is_disabled', 0)
                    ->where('is_dis_award', 0)
                    ->field('id, sg_level')
                    ->select();
        //help_p($res);

        Db::startTrans();
        try {
            foreach ($res as $val) {
                switch ($val['sg_level']){
                    case 0:
                        // 直推总人数 >= 5
                        $pushTotal = $this->pushUsersTrue($val['id'], 5, 0, ['is_dis_award' => 0]);

                        if ($pushTotal > 0)
                        {
                            //help_p('用户'.$val['id'].'-'.$val['sg_level'].'-'.$pushTotal);
                            // 升级当前用户等级为一星
                            $this->updateLevel($val['id'], 1);
                        }
                        break;
                    case 1:
                        // 直推 50 个一星社群
                        $pushTotal = $this->pushUsersTrue($val['id'], 50, 1, ['is_dis_award' => 0]);

                        if ($pushTotal > 0)
                        {
                            //help_p('用户'.$val['id'].'-'.$val['sg_level'].'-'.$pushTotal);
                            // 升级当前用户等级为二星
                            $this->updateLevel($val['id'], 1);
                        } else {
                            // 降星
                            $this->table($this->table)
                                ->where('id', $val['id'])
                                ->setDec('sg_level', 1);

                            help_test_logs(['更新 id-'. $val['id'] .' 星群等级为 0。']);
                        }
                        break;
                    case 2:
                        // 直推 100 个一星社群
                        $pushTotal  = $this->pushUsersTrue($val['id'], 100, 1, ['is_dis_award' => 0]);
                        // 降星
                        $pushTotal2 = $this->pushUsersTrue($val['id'], 50, 1, ['is_dis_award' => 0]);

                        if ($pushTotal > 0)
                        {
                            //help_p('用户'.$val['id'].'-'.$val['sg_level'].'-'.$pushTotal);
                            // 升级当前用户等级为三星
                            $this->updateLevel($val['id'], 1);
                        } elseif ($pushTotal2 < 1) {
                            // 降星
                            $this->table($this->table)
                                ->where('id', $val['id'])
                                ->setDec('sg_level', 1);

                            help_test_logs(['更新 id-'. $val['id'] .' 星群等级为 1。']);
                        }
                        break;
                    case 3:
                        // 直推 100 个一星社群
                        $pushTotal = $this->pushUsersTrue($val['id'], 100, 1, ['is_dis_award' => 0]);

                        if ($pushTotal < 1)
                        {
                            // 降星
                            $this->table($this->table)
                                ->where('id', $val['id'])
                                ->setDec('sg_level', 1);

                            help_test_logs(['更新 id-'. $val['id'] .' 星群等级为 2。']);
                        }
                        break;
                }
            }

            help_test_logs(['会员社群星级自动升降级操作成功']);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();

            help_test_logs([
                '会员社群星级升级操作失败',
                $e->getMessage()
            ]);
        }
    }// socialGroupsLevel() end

    /*
     * 获取伞下用户信息
     * 参数1：int    $tid  jm_members.id
     * 参数2：boolea $bool 是否跳过星级等于 0 的用户，true 跳过
     * return array
     * */
    public function umbrellaUsers(int $tid = 0, $bool = false)
    {
        /*
         * number       WSEC ID
         * relation     关系【直推/联盟】
         * sg_level     等级
         * is_dis_award 是否激活【是/否】
         * */

        if ($bool)
        {
            $where = [
                ['pid', '=', $tid],
                ['sg_level', '>', '0']
            ];
        } else {
            $where = ['pid' => $tid];
        }

        // 获取 $tid 所有的直推
        $res = $this->table($this->table)
                ->where($where)
                ->field('id')
                ->select();

        $push = []; // 直推会员 ID
        foreach ($res as $v) {
            $push[] = $v['id'];
        }

        // 获取 $tid 伞下会员
        $like  = '%-' . $tid . '-%';
        $like2 = $tid . '-%';
        $res2  = $this->table($this->table)
                ->where('relation', 'like', $like)
                ->whereOr('relation', 'like', $like2)
                ->field('id, relation, is_dis_award')
                ->select();

        // 获取 $tid 伞下所有的会员的 ID
        $arrs = [];
        foreach ($res2 as $val) {
            // 将推荐关系由字符串格式转换成索引数组格式
            $arr    = explode('-', $val['relation']);
            $arrs[] = $val['id'];

            foreach ($arr as $val2) {
                // $val2 == $tid 后面开始就是 $tid 的上级了，所以要结束
                if ($val2 == $tid)
                    break;

                $arrs[] = $val2;
            }
        }
        // 删除重复的值
        $arrs = array_unique($arrs);

        // 数据转换
        $data = [];
        foreach ($arrs as $val) {
            if ($bool)
            {
                // 获取 星级 > 0 的用户
                $user = $this->table($this->table)
                    ->where('sg_level', '>',0)
                    ->where('id', $val)
                    ->field('id, number, is_dis_award, sg_level')
                    ->find();
            } else {
                // 获取所有星级的用户
                $user = $this->table($this->table)
                    ->where('id', $val)
                    ->field('id, number, is_dis_award, sg_level')
                    ->find();
            }

            if (empty($user))
                continue;

            // 账号激活状态
            $is_dis_award = $user['is_dis_award'] == 0 ? lang('yes') : lang('no');
            if (in_array($user['id'], $push))
            {
                // 直推会员
                $data[] = [
                    'number'        => $user['number'],    // WSEC ID
                    'relation'      => lang('relation_1'), // 关系
                    'sg_level'      => $user['sg_level'],  // 星级
                    'is_dis_award'  => $is_dis_award,      // 是否激活
                ];
            } else {
                // 联盟会员
                $data[] = [
                    'number'        => $user['number'],    // WSEC ID
                    'relation'      => lang('relation_2'), // 关系
                    'sg_level'      => $user['sg_level'],  // 星级
                    'is_dis_award'  => $is_dis_award,      // 是否激活
                ];
            }
        }
        //help_p(count($data));
        //help_p($data);

        return $data;
    }// umbrellaUsers() end

    /*
     * 获取联盟用户
     * 参数2：string $relation jm_members.relation
     * return array
     * */
    public function allianceUsers(string $relation = '')
    {
        // relation: 26-18-13-8-1-0
        // 获取 $relation 那条线伞下所有用户
        $res = $this->table($this->table)
                    ->where('relation', 'like', '%-'.$relation)
                    ->field('relation')
                    ->select();
        //help_p('获取联盟用户');
        //help_p($res);

        $data = [];
        foreach ($res as $key => $val) {
            // 去除 $relation 的字符
            $str = str_replace('-'.$relation, '', $val['relation']);
            // 将推荐关系由字符串格式转换成索引数组格式
            $arr = explode('-', $str);

            // 将一个或多个单元压入数组的末尾（入栈）
            foreach ($arr as $val2) {
                array_push($data, $val2);
            }
        }

        // 转换后成为以下格式的数组
        /*Array
        (
            [0] => 52
        )*/

        // 删除数组中重复的值
        $data = array_unique($data);
        //help_p($data);

        return $data;
    }// allianceUsers() end

    /*
     * 自动激活和冻结功能
     * 当会员的 chain < 13600 时将冻结，反之将激活
     * */
    public function autoActivate(
        TokenCurrency $TokenCurrency,
        StaticRevenue $StaticRevenue
    ) {
        // 禁用账号不参与自动激活和冻结
        $res = $this->table($this->table)
                    ->where('is_disabled', 0)
                    ->field('id, address')
                    ->select();

        $TokenCurrency  = new TokenCurrency();
        // creator 合约，name 币种名称
        $token          = $TokenCurrency->one(1,'price, rate, creator, name');

        foreach ($res as $val) {
            // 获取链上余额
            $res2 = $StaticRevenue->accountBalance($val['address'], $token);

            if (empty($res2))
            {
                // 冻结账号
                $this->table($this->table)
                    ->where('id', $val['id'])
                    ->update(['is_dis_award' => 1]);

                continue;
            }

            $total = explode(' ', $res2[0])[0];

            if ($this->balance > $total)
            {
                // 冻结账号
                $this->table($this->table)
                    ->where('id', $val['id'])
                    ->update(['is_dis_award' => 1]);

                continue;
            } elseif ($total >= $this->balance) {
                // 解冻账号
                $this->table($this->table)
                    ->where('id', $val['id'])
                    ->update(['is_dis_award' => 0]);
            }
        }

        help_test_logs(['执行自动激活和冻结功能-成功']);
    } // autoActivate() end

}// MembersModel{} end