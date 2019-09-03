<?php

namespace app\index\controller;

use api\common\model\LockedModel;
use Eos\Client;
use Eos\Ecc;
use service\Block;
use think\Controller;
use think\Db;
use btc\Bitcoin;

/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/24
 * Time: 19:08
 */
class IndexController extends Controller
{
    public $url = 'http://127.0.0.1:10002';

    public function test()
    {
        dump(curl()->get("http://cscapi.cscchain.net/action?name=22bvltipys4m&token=vrc.token&size=1100&page=1"));
    }

    public function zingdapp()
    {
        $pid    = input("pid");
        $mobile ='';

        if (!empty($pid))
        {
            $mobile = dbMember("members")->where("id", $pid)->value("mobile");
        }

        $this->assign("mobile", $mobile);

        return $this->fetch();
    }

    public function test_ocx(){
        $secret_key ='eqGDgRcffdWhmzGBRfnZuAvsQP3es3syCh60jkGF';
        $time = time()."000";
        $payload = 'GET|/api/v2/deposits|access_key=L0TAi4hoVC7vWKC2IuitzMQhfH7QhFI4xaZ4Smxx&currency_code=usdt&limit=10&page=1&tonce='.$time;//&limit=2
        $sign = hash_hmac('sha256',$payload,$secret_key);
        $url = 'https://openapi.ocx.app/api/v2/deposits?access_key=L0TAi4hoVC7vWKC2IuitzMQhfH7QhFI4xaZ4Smxx&currency_code=usdt&limit=10&page=1&tonce='.$time.'&signature='.$sign;
        //var_dump($url);
        $data = curl_get($url);
        var_dump($data);
        exit();
    }

    public function getbroadcast()
    {
        $news = Db::name('news')->where("id", 1)->cache(60)->find();
        //规则
        //$data['broadcast'] = strip_tags(str_replace("</p><p>", "\n", $news['content']));
        $data['broadcast'] = $news['content'];
        $data['news_img'] = empty($news['news_img'])?'':str_replace('/api.php','',cmf_get_image_preview_url($news['news_img']));
        $this->assign("data", $data);
        return $this->fetch();
    }
    public function  get_pwd(){
        var_dump(cmf_password("000000"));
    }

    public function vote(){
        $reids = redis();
        $data = input();
        $uid = '';
        $type = 0;
        $token = '';
        if(!empty($data['token'])) {
            $token = $data['token'];
            $uid = $reids->get("members:" . $data['token']);
        }
        if($uid){
            $vote = Db::name("vote")->where("mid",$uid)->find();
            if(!empty($vote)) {
                $type = $vote['type'];
            }
        }
        if(!empty($data['type'])){
            $this->error("投票失败,投票已截止");
            if($data['type'] != 1 && $data['type'] != 2){
                $this->error("投票失败");
            }
            if($uid){
                if($type>0){
                    $this->error("您已投过票,请勿重复投票");
                }
                $in_data = ['mid'=>$uid,'type'=>$data['type']];
                $res = Db::name("vote")->insert($in_data);
                if($res){
                    $all_type = Db::name("vote")->where("type",$data['type'])->count();
                    $this->success("投票成功","",$all_type);
                }else{
                    $this->error("投票失败");
                }
            }else{
                $this->error("请先登录");
            }
        }
        $all_type1 = Db::name("vote")->where("type",1)->count();
        $all_type2 = Db::name("vote")->where("type",2)->count();
        $this->assign("all_type1",$all_type1+308);
        $this->assign("all_type2",$all_type2+220);
        $this->assign("token",$token);
        $this->assign("vote_type",$type);
        return $this->fetch();
    }

    public function get_all_token($page=1){
        $block = new Block();
        $LockedModel = new LockedModel();
        //可用zing
        $list = Db::name("members")->field("id")->where("is_gyb",0)->limit(50)->select()->toArray();
        if(empty($list)){
            die();
        }
        foreach ($list as $k =>$v){
            $zing = $block->getUserZing($v['id']);
            //$lockzing = $LockedModel->getLocked($v['id']);
            $data = ['gyc_wallet'=>$zing,"is_gyb"=>1];
            Db::name("members")->where("id",$v['id'])->update($data);
        }
        $page++;
        $this->success("当前页".$page,$this->url."/index/index/get_all_token/page/".$page,'',2);
    }

    public function create_wallet($page){
        return;
        $list = Db::name("members")->field("mobile")->where(['address'=>''])->limit(50)->select()->toArray();
        if(empty($list)){
            die();
        }
        foreach ($list as $k =>$v){
            $param = [
                'mobile' => $v['mobile']
            ];
            $url = request()->domain();
            //curl()->post($url . "/api.php/block/suntoken/create_account", $param);
            $data = curl_post($url . "/api.php/block/suntoken/create_account", $param);
            var_dump($data);
        }
        $page++;
        $this->success("当前页".$page,$this->url."/index/index/create_wallet/page/".$page,'',2);
    }

    public function zz_account($page){
        return;
        $list = Db::name("members")->field("address,gyc_balance")->where(["is_gyb"=>1])->where('gyc_balance',">",0)->limit(50)->select()->toArray();
        if(empty($list)){
            die();
        }
        foreach ($list as $k =>$v){
            $param = [
                'address' => $v['address'],
                'money' => $v['gyc_balance']
            ];
            $url = request()->domain();
            //curl()->post($url . "/api.php/block/suntoken/create_account", $param);
            $data = curl_post($url . "/index/index/transaction", $param);

        }
        $page++;
        $this->success("当前页".$page,$this->url."/index/index/zz_account/page/".$page,'',2);
    }

    public function get_account($mobile = '')
    {
        $random_array = array("q", "a", "z", "5", "w", "s", "x", "e", "d", "c", "1", "r", "f", "v", "t", "g", "b", "y", "h", "n", "2", "u", "j", "m", "4", "i", "k", "o", "3", "l", "p");
        $account = "";
        while ($mobile > 30) {
            $key = bcmod($mobile, 30,0);
            $key = bcmul($key,1,0);
            $account .= $random_array[$key];
            $mobile = bcdiv(bcsub($mobile, $key), 30,0);
            $mobile = bcmul($mobile,1,0);
        }
        $account =  "p".$account.$random_array[$mobile];
        $length = strlen($account);
        $s_length = 12 - $length;
        $i = 0;
        while ($i < $s_length) {
            $ran = mt_rand(0, 29);
            $account = $random_array[$ran].$account;
            $i++;
        }
        return $account;
    }

    public function test_account($mobile = '')
    {
        // 新建账号
        $c_url = "http://rpc.zdtchain.com:8888";
        $c_private ="5Jjudwmud7mM6hQxRU92iEQsJYjwjG7d1jMhUhQ8HZaes5t8KjJ";
        $client = new Client($c_url);
        $newAccount = "gozhenwuswgo";
        //$newAccount = $this->get_account("17070940570");
        //echo "account:".$newAccount."<br/>";
        //$active_private = Ecc::randomKey();
        //$activePublicKey = Ecc::privateToPublic($active_private);
        $activePublicKey = 'ZDT5kXJ38VFPfziqUJJjvY23Z8yySFX18gDCnMejERbUDhab4Xsg6';
        /*var_dump("active_private：".$active_private."<br/>");
        var_dump("activePubilc：".$activePublicKey."<br/>");*/
        $owner_private = Ecc::randomKey();
        $ownerPublicKey = Ecc::privateToPublic($owner_private);
        var_dump("owner_private：".$owner_private."<br/>");
        var_dump("ownerPublicKey：".$ownerPublicKey."<br/>");
        $data = array("address" => $newAccount, "active_private" => '', "activePublicKey" => $activePublicKey, "owner_private" => $owner_private, "ownerPublicKey" => $ownerPublicKey);
        $tx = $client->addPrivateKeys([$c_private])->transaction([
            'actions' => [
                [
                    'account' => 'zdtio',
                    'name' => 'newaccount',
                    'authorization' => [[
                        'actor' => 'tonydchan123',
                        'permission' => 'active',
                    ]],
                    'data' => [
                        'creator' => 'tonydchan123',
                        // Main net key is name
                        'name' => $newAccount,
                        'owner' => [
                            'threshold' => 1,
                            'keys' => [
                                ['key' => $ownerPublicKey, 'weight' => 1],
                            ],
                            'accounts' => [],
                            'waits' => [],
                        ],
                        'active' => [
                            'threshold' => 1,
                            'keys' => [
                                ['key' => $activePublicKey, 'weight' => 1],
                            ],
                            'accounts' => [],
                            'waits' => [],
                        ],
                    ],
                ],
                [
                    'account' => 'zdtio',
                    'name' => 'buyrambytes',
                    'authorization' => [[
                        'actor' => 'tonydchan123',
                        'permission' => 'active',
                    ]],
                    'data' => [
                        'payer' => 'tonydchan123',
                        'receiver' => $newAccount,
                        'bytes' => 70000,
                        //'bytes' => '1.0001 ZDT'
                    ],
                ],
                [
                    'account' => 'zdtio',
                    'name' => 'delegatebw',
                    'authorization' => [[
                        'actor' => 'tonydchan123',
                        'permission' => 'active',
                    ]],
                    'data' => [
                        'from' => 'tonydchan123',
                        'receiver' => $newAccount,
                        'stake_net_quantity' => '1.1000 ZDT',
                        'stake_cpu_quantity' => '1.1000 ZDT',
                        'transfer' => false,
                    ],
                ]
            ]
        ]);
        $data['transaction_id'] = $tx->transaction_id;
        if ($data['transaction_id']) {
           /* dbMember('members')->where(array("mobile" => $mobile))->update($data);*/
           // return $this->success("创建成功");
        }
    }

    //获取账户余额
    public function get_token($account='', $id = '')
    {
        $account = 'ry1vs22plzu1';
        $c_url = "http://rpc.zdtchain.com:8888";
        $client = new Client($c_url);
        $chain = $client->chain();
        $where = array("status" => 1);
        if (!empty($id)) {
            $where['id'] = $id;
        }
        $tokens = array();
        $tokens[] = ['creator'=>'tonydchan123','name'=>"WSEC"];
        $data = array();
        foreach ($tokens as $k => $token) {
            $res = $chain->get_currency_balance(['code' => $token['creator'], 'symbol' => $token['name'], "account" => $account]);
            if (!empty($res)) {
                $cc = explode(" ", $res[0]);
                $token['balance'] = ($cc[0] * 1) . "";
                $token['balance_price'] = round($cc[0] * $token['price'], 2) . "";
            } else {
                $token['balance'] = "0";
                $token['balance_price'] = "0";
            }
            $token['image'] = empty($token['image']) ? '' : cmf_get_image_preview_url($token['image']);
            $data[] = $token;
        }
        if (!empty($id)) {
            if (!empty($data)) {
                return $data[0];
            }
        }
        return $data;
    }

    //转账
    public function transaction()
    {
        //$data = $this->request->param();
        $c_url = "http://rpc.zdtchain.com:8888";
        $client = new Client($c_url);
        $currency = ['creator'=>'tonydchan123','name'=>"WSEC"];
        // 新建账号
        $client->addPrivateKeys(["5Jjudwmud7mM6hQxRU92iEQsJYjwjG7d1jMhUhQ8HZaes5t8KjJ"]);
        //$client->addPrivateKeys(["5JMyCzqfP6gcqJ1KqFNQBd3KF1bTyXodksibhN5H7Qz6p1Au2Xf"]);
        $account = "tonydchan123";
        //$account = 'i1fzsl1tf55k';
        $to_address = "uptqkjy54fer";
        $money = number_format('2069763243.78182786', 8, ".", "");
        $money = $money . " " . $currency['name'];
        $type = $currency['creator'];
        $actions = [[
            'account' => $type,
            'name' => 'transfer',
            'authorization' => [[
                'actor' => $account,
                'permission' => 'active',
            ]],
            'data' => [
                'from' => $account,
                'to' => $to_address,
                'quantity' => $money,
                'memo' => "aa",
            ],
        ]];
        $tx = $client->transaction([
            'actions' => $actions
        ]);
        $data1 = array();
        $data1['transaction_id'] = $tx->transaction_id;
        $data1['block_num'] = $tx->processed->block_num;
        if(!empty($data1)){
            dbMember('members')->where(array("address" => $data['address']))->update(['is_gyb'=>2]);
        }
        //return $this->success("信息返回", $data);
    }

    /**
     *  创建eth
     */
    public function get_eth(){
        $list = Db::name("members m")->join("sun_account_address as aa","aa.mid = m.id","left")->find("aa.id,aa.mid")->where('aa.id is null')->limit(1)->select()->toArray();
        var_dump($list);
    }

    public function  test_btc(){
        $bitcoin = new Bitcoin('admin','admin123456','47.75.57.126','8889');
        $res= $bitcoin->omni_getinfo();
        echo(json_encode($res));
    }
}