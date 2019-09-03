<?php
/*
 * 会员社群星级自动晋升
 * 作者：ck
 * */

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\admin\model\MembersModel as Members;

class MembershipPromotion extends Command
{
    protected function configure()
    {
        $this->setName('MembershipPromotion')->setDescription('会员社群星级自动晋升自定义任务');
    }// configure() end

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);// 设置脚本最大执行时间-不限制

        $output->writeln('会员社群星级自动晋升自定义任务 - 正在执行中！');

        $Members = new Members();
        $Members->socialGroupsLevel(); // 会员社群星级升级
        $Members = null;
    }// execute() end
}