<?php

namespace app\common\controller;

/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 12:44
 */
use cmf\controller\BaseController as Base;
use think\facade\Env;

class BaseController extends Base
{
    public $config = [];

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub

        $this->config = webConfig();
        $this->project = Env::get("project");
    }

}