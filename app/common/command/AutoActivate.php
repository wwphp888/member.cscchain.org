<?php
/*
 * 静态收益自定义任务
 * 作者：ck
 * */

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\admin\model\MembersModel as Members;
use app\admin\model\TokenCurrencyModel as TokenCurrency;
use app\admin\controller\StaticRevenueController as StaticRevenue;

class AutoActivate extends Command
{
    protected function configure()
    {
        $this->setName('AutoActivate')->setDescription('会员自动激活解冻自定义任务');
    }// configure() end

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);// 设置脚本最大执行时间-不限制

        $output->writeln('会员自动激活解冻自定义任务 - 正在执行中！');

        $task          = new Members();
        $TokenCurrency = new TokenCurrency();
        $StaticRevenue = new StaticRevenue();

        $task->autoActivate($TokenCurrency, $StaticRevenue);
        $task = null;
    }// execute() end
}// StaticRevenue{} end