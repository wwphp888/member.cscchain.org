<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 10:41
 */

namespace app\admin\controller;

use app\common\controller\AdminBaseController;
use app\admin\model\ConfigModel;

class ConfigController extends AdminBaseController
{
    public function index()
    {

        $config = new ConfigModel();
        if (request()->isPost()) {
            if ($config->saveConfig(input()) === true) {
                $this->success(lang('set_1'));
            } else {
                $this->error($config->getError());
            }
        } else {
            return $this->fetch();
        }

    }
}