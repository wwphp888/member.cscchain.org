<?php
namespace app\admin\controller;

use app\admin\controller\StaticRevenueController as StaticRevenue;
use app\admin\model\TokenCurrencyModel as TokenCurrency;
use app\common\controller\AdminBaseController;
use service\Block;
use think\Db;
use app\admin\model\MembersModel as Members;
use think\Request;

class MembersController extends AdminBaseController
{
	public function index(
	    Members       $Members,
        TokenCurrency $TokenCurrency,
        StaticRevenue $StaticRevenue,
        Request       $request
    ) {
        // creator 合约，name 币种名称
        $token = $TokenCurrency->one(1,'price, rate, creator, name');
        if (empty($token))
            return $this->error(lang('error'));

        // 查询条件
        $where = [];

        // 会员 ID
        if (empty($request->get('number')) === false && is_numeric($request->get('number')))
            $where[] = ['number', '=', trim($request->get('number'))];

        // 手机号
        if (empty($request->get('mobile')) === false && is_numeric($request->get('mobile')))
            $where[] = ['mobile', '=', trim($request->get('mobile'))];

        // 钱包地址
        if (empty($request->get('address')) === false && is_string($request->get('address')))
        {
            $where[] = ['address', '=', trim($request->get('address'))];
        }

        /*
         * id           表ID
         * number       会员ID
         * is_disabled  禁用：1-禁用，0-启用
         * is_dis_award 激活：1-冻结，0-激活
         * address      钱包地址
         * mobile       手机号
         * sg_level     星级
         * profit       静态收益【总】
         * dynamic      动态收益【总】
         * sg_profit    社群收益【总】
         * balance      chain 余额
         * last_login   最后登录时间
         * */
        $res = $Members
                    ->where($where)
                    ->field('id, number, is_disabled, is_dis_award, address, balance, mobile, sg_level, profit, dynamic, sg_profit, last_login')
                    ->order('id', 'desc')
                    ->paginate(15);
        //help_p($Members->getLastSql());

        foreach ($res as $key => $val) {
            $res[$key]['is_disabled']  = $val['is_disabled']  == 0 ? '否' : '是';
            $res[$key]['is_dis_award'] = $val['is_dis_award'] == 0 ? '否' : '是';

            /*
             * 获取单个用户的余额
             * 获取的链余额是 '10000.0000 WSEC' 的格式
             * 故用 explode() 转成数组
             * */
            $balance = $StaticRevenue->accountBalance($val['address'], $token);
            if (empty($balance))
            {
                $total = 0.0000;
            } else {
                $total = explode(' ', $balance[0])[0];
            }

            $res[$key]['balance']    = $total;
            $res[$key]['last_login'] = date('Y-m-d', $val['last_login']);
        }

        $this->assign('list',$res);

		return $this->fetch();
    }// index() end

    /*
     * 对会员进行 禁用和冻结 操作
     * */
    public function stateChanges(Members $Members, Request $request)
    {
        /*
         * id           会员 ID
         * is_disabled  禁用
         * is_dis_award 冻结
         * */
        $uid            = $request->get('id');
        $state          = $request->get('state');
        $where          = [];
        $condition      = ['is_disabled', 'is_dis_award'];

        // $uid 不能为空
        if (empty($uid) === false && is_numeric($uid))
            $where = ['id' => $uid];

        // 获取用户记录
        $res = $Members->field('is_disabled, is_dis_award')->where($where)->find();

        // 记录不存在报错
        if (empty($res))
            return $this->error(lang('error'));

        // 禁用和冻结不允许为空
        if (empty($state))
            return $this->error(lang('error'));

        // 禁用 or 冻结 操作
        if (in_array($state, $condition))
        {
            $is_state = $res[$state] == 1 ? 0 : 1;
            $Members->where($where)->update([$state => $is_state]);

            return $this->success(lang('success'));
        }

        return $this->error(lang('error'));
    }// stateChanges() end

    //修改会员信息
    public function userinfo()
    {
        $param = $this->request->param();

        if (empty($param['bankname']))
        {
            unset($param['bankname']);
        }

        $res = dbMember('members')
                ->where([
                    'id'=>$param['id']
                ])
                ->update($param);

        if ($res == true)
        {
            $this->success('修改成功');
        }else{
            $this->error('并没有什么修改');
        }
    }// userinfo() end

    /**
     * 禁用/删除
     */
    public function changeStatus()
    {
        $param = $this->request->param();

        if(empty($param['id']))
        {
            $this->error('信息不全');
        }

        $userInfo = dbMember('members')
                    ->field('id,is_dis_award,is_disabled')
                    ->where('id',$param['id'])
                    ->find();

        if($param['type'] == 'is_dis_award')
        {
			if($userInfo['is_dis_award']==0)
			{
				$is_dis_award = 1;
			}else{
				$is_dis_award = 0;
			}

			$res = dbMember('members')
                    ->where('id', $param['id'])
                    ->setField('is_dis_award', $is_dis_award);

		} elseif($param['type'] == 'is_disabled') {
			if($userInfo['is_disabled']==0)
			{
				$is_disabled = 1;
			} else {
				$is_disabled = 0;
			}

			$res = dbMember('members')
                    ->where('id', $param['id'])
                    ->setField('is_disabled', $is_disabled);
		}

        if($res === false)
        {
            $this->error('操作失败');
        }

        $this->success('操作成功');
    }// changeStatus() end

    public function alterPwd()
    {
        if($this->request->isAjax())
        {
            $param = $this->request->param();

            if($param['pwd'] !== $param['repwd'])
            {
                $this->error('两次密码不一致');
            }
            if($param['type'] == 'login')
            {
                $data = [
                    'login_pwd' => cmf_password($param['pwd'])
                ];
            } elseif($param['type'] == 'trade') {
                $data = [
                    'trade_pwd' => cmf_password($param['pwd'])
                ];
            } else {
                $this->error('网络错误');
            }

            dbMember('members')
                ->where('id', $param['id'])
                ->update($data);

            // ($data,['id'=>$param['id']]);
            $this->success('修改成功');
        }
    }// alterPwd() end

    /**
     * 会员关系树
     */
    public function relation()
    {
        set_time_limit(0);
        ini_set ('memory_limit', '250M');

        $project_id = session("project_id");
        $where      = "project='".$this->project."'";

        if($this->request->isAjax())
        {
            $_GPC = $this->request->param();

            if(isset($_GPC['od']))
            {
                $data       = [];
                $userData   = [];

                switch ($_GPC['od'])
                {
                    case "get_node":
                        $node = isset($_GPC['id']) ? $_GPC['id'] : 0;
                        $temp = dbMember('members')
                                    ->where($where)
                                    ->field('id,relation,realname,avatar,mobile,number')
                                    ->select();

                        foreach($temp as $key=>$val){
                            if(!empty($val['avatar']))
                            {
                                $val["text"] ='<img width="24" style="border-radius:50%;" src="'.$val['avatar'].'"> ';
                            } else {
                                $val["text"] = '<img width="20" src="'.'/static/images/iconfont-user.png'.'"> ';
                            }

                            $val["text"] = '<img width="20" src="'.'/static/images/iconfont-user.png'.'"> ';

                            if(!empty($val['realname']))
                            {
                                $val["text"] .=$val['mobile']. "[".$val['realname']."]<e style='color:#aaa;font-size:10px'>&lt;UID" . $val['id'] . "&gt;</e><e style='color:#6173ef;font-size:10px'>&lt;number" . $val['number'] . "&gt;</e>";
                            } else {
                                $val["text"] .= $val['mobile'] . "<e style='color:#aaa;font-size:10px'>&lt;UID" . $val['id'] . "&gt;</e>". "<e style='color:#6173ef;font-size:10px'>&lt;number" . $val['number'] . "&gt;</e>";
                            }

                            $val["id"]              = $val['id'];
                            $userData[$val['id']]   = $val;
                        }

                        $data[] = [
                            'id'        => "0",
                            'text'      => "<img width=\"24\" src=\"/static/images/iconfont-tree.png\"> 平台",
                            "state"     => ["opened" => true],
                            'children'  => disTree($userData),
                            "type"      => "root"
                        ];

                        break;
                    default:
                        $data = "error";
                        break;
                }
                return $data;
            }
        }

        return $this->fetch();
    }// relation() end

    public function changeNode()
    {
        $param = $this->request->param();
        if(empty($param['id'])){
            $this->error('信息不全');
        }
        $nodeInfo = Db::name('node')->where('mid',$param['id'])->find();
        if($param['type'] == 'is_node'){
            if(empty($nodeInfo)){
                $relation = dbMember('members')->where(['id'=>$param['id']])->value('relation');
                $data = [
                    'mid'=>$param['id'],
                    'status'=>1,
                    'create_time'=>time(),
                    'relation'=>$relation
                ];

                $res = Db::name('node')->insert($data);
            }else{
                $status = $nodeInfo['status']?0:1;
                $res = Db::name('node')->where(['mid'=>$param['id']])->update(['status'=>$status]);
            }
        }
        if($res === false){
            $this->error('操作失败');
        }
        $this->success('操作成功');    
    }// changeNode() end
}// MembersController{} end

function disTree($data)
{
    $tree = [];

    foreach ($data as $item) {
        if($item['relation'] !== "0")
        {
            preg_match('/^\d*-/', $item['relation'], $matches);
            $pid = trim($matches[0],"-");
        } else {
            $pid = 0;
        }
        
        if (!empty($data[$pid]))
        {
            $data[$pid]['children'][]   = &$data[$item['id']];
            $data[$pid]['num']          = count($data[$pid]['children']);

            $img = '<img width="20" src="'.'/static/images/iconfont-user.png'.'"> ';
            $data[$pid]['text'] = $img.$data[$pid]['mobile']. "[".$data[$pid]['realname']."]<e style='color:#aaa;font-size:10px'>&lt;UID" . $data[$pid]['id'] . "&gt;</e>" . "<e style='color:#6173ef;font-size:10px'>&lt;number" . $data[$pid]['number'] . "&gt;</e>" . "<e style='color:#f50;font-size:10px'>&lt;人数" . $data[$pid]['num'] . "&gt;</e>";
        } else {
            $tree[] = &$data[$item['id']];
        }
    }
    return $tree;
}