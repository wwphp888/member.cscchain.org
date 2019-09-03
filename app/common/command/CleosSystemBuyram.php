<?php
/*
 * 社群收益自定义任务
 * 作者：ck
 * */

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\admin\model\MembersModel as Members;

class CleosSystemBuyram extends Command
{
    protected function configure()
    {
        $this->setName('CleosSystemBuyram')->setDescription('WSEC 算力值购买自定义任务');
    }// configure() end

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);// 设置脚本最大执行时间-不限制

        $output->writeln('WSEC 算力值购买自定义任务 - 正在执行中！');

        $Members = new Members();
        // 获取所有用户
        $users = $Members->field('id, mobile, address')->select();

        foreach ($users as $val) {
            if (empty($val['address']))
                continue;

            // 执行记录
            $str = $val['id'].'-'.$val['mobile'].'的钱包地址:'.$val['address'].'购买算力值 3000.0000 SYS';
            help_test_logs([$str]);

            $exec = "cleos system buyram eosio ".$val['address']." '3000.0000 SYS'";

            $output->writeln('正在执行指令：'.$exec);

            // 执行
            $c = shell_exec($exec);
            help_test_logs([$exec, $c]);
            
            sleep(1);
        }

        $output->writeln('WSEC 算力值购买自定义任务 - 执行完毕！');
    }// execute() end
}// SocialGroupsRevenue{} end