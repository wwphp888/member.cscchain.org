<?php

namespace api\index\controller;

/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/20
 * Time: 15:32
 */
use  api\common\controller\ApiController;

class IndexController extends ApiController
{
    public function test()
    {
//        echo 111;
        getFloor(14);
    }



    public function getVersion()
    {
       /* $data = [
            'version' => $this->config['version'],
            'url' => $this->config['version_url'],
            'description' => $this->config['version_content'],
        ];*/
        $data = [
            'version'       => '1.0.0',
            'url'           => 'https://dow.zingdapp.com/ZINGDAPP/',
            'description'   => '优化红包功能',
        ];
        $this->success($data);
    }

    public function getProtocol()
    {
        $data['protocol'] = htmlspecialchars_decode($this->config['user_agreement']??'');
        $this->success($data);
    }
}