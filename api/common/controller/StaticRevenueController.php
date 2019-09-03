<?php
/*
 * 静态收益
 * 作者：ck
 * */

namespace api\common\controller;

use think\Db;
use api\common\model\MembersModel as Members;
use api\common\model\TokenCurrencyModel as TokenCurrency;
use api\common\model\StaticRevenueTaskLogsModel as StaticRevenueTaskLogs;
use api\common\model\StaticRevenueInterestLogsModel as StaticRevenueInterestLogs;
use Eos\Client;
use service\Block;
use api\common\controller\ApiController;

class StaticRevenueController extends ApiController
{
    protected $price    = 0;    // jm_token_currency.price 单价
    protected $rate     = 0;    // jm_token_currency.rate 收益利率
    protected $days     = 30;   // 静态收益默认天数
    protected $taskLogs = null; // 静态收益定时任务执行记录表对象
    protected $limit    = 1000; // 每次获取的记录条数
    protected $uids     = [8];  // 黑名单用户 ID

    public function __construct()
    {
        $this->taskLogs = new StaticRevenueTaskLogs();
    }// __construct() end

    /*
     * 静态收益任务-每日执行一次
     * */
    public function task()
    {
        // 防止单日重复结算
        if ($this->taskLogs->today()) {
            $this->taskLogs->addLogs('stop');// 静态收益任务-终止
            return null;
        }

        $this->taskLogs->addLogs('start');// 静态收益任务-开始

        $Members    = new Members();// 获取会员数据
        $total      = $Members->totalQuantity(['is_disabled' => 0]);// 获取会员总人数
        $times      = ceil($total / $this->limit);
        $offset     = 0;

        for($i=0; $i<$times; $i++){
            $offset = $i * $this->limit;
            //help_p($offset);

            // 获取用户数据
            $res2 = $Members->all('id, address', ['is_disabled' => 0], $offset, $this->limit);
            //echo Db::name('members')->getLastSql();

            $TokenCurrency  = new TokenCurrency();
            // creator 合约，name 币种名称
            $token          = $TokenCurrency->one(1,'price, rate, creator, name');

            if (empty($token)) {
                return false;
            }

            // 获取比率
            $this->rate     = $token['rate'] / 100;
            $InterestLogs   = new StaticRevenueInterestLogs();// 静态收益用户记录表对象
            $insert         = [];// 需要写入的数据

            Db::startTrans();// 启动事务
            try{
                foreach($res2 as $key => $val) {
                    //help_p($val);
                    // 跳出黑名单用户
                    if (in_array($val['id'], $this->uids))
                        continue;

                    // 获取链上余额
                    $res3 = $this->accountBalance($val['address'], $token);

                    // 跳过余额为 0 的用户
                    if (empty($res3)) {
                        continue;
                    }
                    //help_p($res3);

                    /*
                     * 获取的链余额是 '10000.0000 WSEC' 的格式
                     * 故用 explode() 转成数组
                     * */
                    // 获取单个用户的余额
                    $total      = explode(' ', $res3[0])[0];
                    if ($total <= 0)
                        continue;

                    // 通过会员现有余额计算利息
                    $interest   = $this->staticBenefitAlgorithms($total, $this->rate);

                    // 将收益记录写入 jm_members.profit 内
                    $success = $Members->addProfit($val['id'], $interest);
                    if ($success > 0)
                    {
                        $insert[] = [
                            'members_id' => $val['id'],
                            't_profit'   => $interest
                        ];
                    }
                }

                //help_p($insert);
                // 写入单个用户的静态收益记录，方便查询
                if (empty($insert) === false) {
                    $InterestLogs->addStaticRevenue($insert);
                }

                Db::commit();// 提交事务
            }catch(\Exception $e){
                Db::rollback();// 回滚事务
                help_test_logs(['静态收益任务错误', $e->getMessage()]);

                // 静态收益任务-错误【将错误进行记录】
                $this->taskLogs->addLogs('err');
            }
        }

        $this->taskLogs->addLogs('end');// 静态收益任务-结束
    }// task() end

    /* 获取用户 WSEC 账户余额
     * 参数1：String $account 钱包地址
     * 参数2：array  $token   币种信息
     * */
    public function accountBalance(string $account = '', $token = [])
    {
        $c_url = config('site.transfer_url');
        $client = new Client($c_url);// 需要做单例

        // 在链上获取用户的余额
        $res = $client->chain()->get_currency_balance([
            'code'      => $token['creator'],
            'symbol'    => $token['name'],
            "account"   => $account
        ]);

        return $res;
    }// accountBalance() end

    /*
     * 静态收益算法
     * 参数1：float $chain 链余额
     * 参数2：float $rate  比率
     * return float
     * 算法：链余额 * (比率 / 30) = 今日结算
     * */
    public function staticBenefitAlgorithms(float $chain = 0.00, float $rate = 0.00)
    {
        $num = $chain * ($rate / $this->days);

        // 返回小数点后四位
        return sprintf("%.4f", $num);
    }// staticBenefitAlgorithms() end

}// StaticRevenueController{} end