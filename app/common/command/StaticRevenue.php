<?php
/*
 * 静态收益自定义任务
 * 作者：ck
 * */

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\admin\controller\StaticRevenueController as STR;

class StaticRevenue extends Command
{
    protected function configure()
    {
        $this->setName('StaticRevenue')->setDescription('静态收益自定义任务');
    }// configure() end

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);// 设置脚本最大执行时间-不限制

        $output->writeln('静态收益自定义任务 - 正在执行中！');

        $task = new STR();
        $task->task();
        $task = null;
    }// execute() end
}// StaticRevenue{} end