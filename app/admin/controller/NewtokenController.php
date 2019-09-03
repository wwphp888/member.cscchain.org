<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 14:17
 */

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use app\admin\model\TokenCurrencyModel;

class NewtokenController extends AdminBaseController
{
    public function currencylist()
    {
        $where = [];
        $TokenCurrencyModel = new TokenCurrencyModel();
        $list = $TokenCurrencyModel->where($where)
            ->field('*')
            ->order("id DESC")
            ->paginate(10);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        // 渲染模板输出
        return $this->fetch();
    }

    public function add()
    {

        return $this->fetch();
    }

    /**
     *添加提交
     */
    public function addPost()
    {

        $TokenCurrencyModel = new TokenCurrencyModel();

        if ($this->request->isPost()) {
            $data = $this->request->param();

            $TokenCurrencyModel->tokenAdd($data['post']);
            $id = $TokenCurrencyModel->id;

            if ($id !== false) {
                $this->success('添加成功!', url('Newtoken/currencylist'));
            }

        }
    }

    /**
     * 修改显示
     */
    public function edit()
    {

        $TokenCurrencyModel = new TokenCurrencyModel();
        $id = $this->request->param('id', 0, 'intval');
        $post = $TokenCurrencyModel->where('id', $id)->find();
        $where = [];
        $where['delete_time'] = '0';

        $this->assign('post', $post);

        return $this->fetch();
    }

    /**
     * 提交
     */
    public function editPost()
    {

        if ($this->request->isPost()) {
            $data = $this->request->param();
            $post = $data['post'];

            $TokenCurrencyModel = new TokenCurrencyModel();

            $TokenCurrencyModel->tokenEdit($data['post'], $data['post']['id']);

            $this->success('保存成功!', url('Newtoken/currencylist'));

        }
    }
}