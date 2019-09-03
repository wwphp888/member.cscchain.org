<?php
/*
 * 动态收益自定义任务
 * 作者：ck
 * */

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\admin\controller\DynamicRevenueController as Drc;

class DynamicRevenue extends Command
{
    protected function configure()
    {
        $this->setName('DynamicRevenue')->setDescription('动态收益自定义任务');
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);// 设置脚本最大执行时间-不限制

        $output->writeln('动态收益自定义任务 - 正在执行中！');

        $task = new Drc();
        $task->task();
        $task = null;
    }// execute() end
}// StaticRevenue{} end