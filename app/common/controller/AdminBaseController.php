<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 12:51
 */

namespace app\common\controller;

use  cmf\controller\AdminBaseController as AdminBase;
use think\facade\Env;

class AdminBaseController extends AdminBase
{
    public $config = [];

    public $project = '';

    public function initialize()
    {

        $config = webConfig();

        $this->config = $config;
        $this->project = Env::get("project");
        $this->assign("config", $config);
        parent::initialize(); // TODO: Change the autogenerated stub

    }

}