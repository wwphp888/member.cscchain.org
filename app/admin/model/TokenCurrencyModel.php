<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\admin\model;


use think\Model;

class TokenCurrencyModel extends Model
{
    protected $table = 'jm_token_currency';// 表全名
    protected $pk    = 'id';// 表主键
    protected $type  = [
        'more' => 'array',
    ];

    /*
     * 获取单条记录
     * 参数1：int      $tid    jm_token_currency.id
     * 参数2：string   $column 表字段
     * */
    public function one(int $tid = 0, string $column = '*')
    {
        $res = $this->table($this->table)
                ->where('id', $tid)
                ->field($column)
                ->find();

        return $res;
    }// getOne() end

    public function tokenAdd($data)
    {
        foreach ($data as $key => $vo) {
            $data[$key] = trim($vo);
        }
        $data['create_time'] = time();
        $data['update_time'] = time();
        $this->allowField(true)->data($data, true)->isUpdate(false)->save();
        return $this;

    }


    /**
     * 修改
     */
    public function tokenEdit($data, $id)
    {
        foreach ($data as $key => $vo) {
            $data[$key] = trim($vo);
        }
        $data['create_time'] = time();
        $data['update_time'] = time();
        $this->allowField(true)->data($data, true)->isUpdate(true)->save();
        return $this;

    }


}