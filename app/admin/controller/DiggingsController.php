<?php

namespace app\admin\controller;
use cmf\controller\AdminBaseController;
use app\admin\model\DiggingsModel;
use think\Db;
use think\db\Query;
class DiggingsController extends AdminBaseController{
	public function test(){
		$model = new DiggingsModel;
		//'2019-05-11 18:05:00'
		$result = $model->runLottery();
	}
}
