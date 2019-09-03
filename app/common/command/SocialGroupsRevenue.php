<?php
/*
 * 社群收益自定义任务
 * 作者：ck
 * */

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\admin\controller\SocialGroupsController as SGR;

class SocialGroupsRevenue extends Command
{
    protected function configure()
    {
        $this->setName('SocialGroupsRevenue')->setDescription('社群收益自定义任务');
    }// configure() end

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);// 设置脚本最大执行时间-不限制

        $output->writeln('社群收益自定义任务 - 正在执行中！');
//return null;
        $task = new SGR();
        $task->task();
        $task = null;
    }// execute() end
}// SocialGroupsRevenue{} end