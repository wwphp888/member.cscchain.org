<?php
/**API基类
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 15:31
 */

namespace api\common\controller;

use service\User;
use think\exception\HttpResponseException;
use think\facade\Env;
use think\facade\Lang;
use think\Response;


class ApiController
{
    public $config = [];
    /**
     * @var \think\Request Request实例
     */
    protected $request;

    public $data    = '';
    public $lang    = 'cn';
    public $project = '';

    public function __construct()
    {
        //屏蔽查看服务器
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
            exit;
        }
        //获取配置项
        $this->config       = webConfig();
        $this->data         = input();
        $this->request      = \request();
        $this->data['lang'] = strip_tags($this->data['lang']??'');

        if (!empty($this->data['lang']))
        {
            $this->lang = $this->data['lang'];
        }
        if (isset($this->data['lang']) && !empty($this->data['lang']))
        {
            $file = APP_PATH . "lang/" . $this->data['lang'] . ".php";
            if (is_file($file))
            {
                Lang::load($file);
            } else {
                Lang::load(APP_PATH . "lang/cn.php");
            }
        } else {
            Lang::load(APP_PATH . "lang/cn.php");
        }
        $this->project = Env::get("project");
        $this->initialize();
    }

    protected function initialize()
    {

    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param mixed $data 返回的数据
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function success($msg = '', $data = '', array $header = [], $code = 1)
    {
        if (is_array($msg) || is_object($msg)) {
            $data   = $msg;
            $msg    = '成功';
        }
        $result = [
            'code'  => $code,
            'msg'   => $msg,
            'data'  => $data,
        ];

        $type = $this->getResponseType();

        $header['Access-Control-Allow-Origin']  = '*';
        $header['Access-Control-Allow-Headers'] = 'X-Requested-With,Content-Type,token';
        $header['Access-Control-Allow-Methods'] = 'GET,POST,PATCH,PUT,DELETE';

        $response = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息,若要指定错误码,可以传数组,格式为['code'=>您的错误码,'msg'=>'您的错误消息']
     * @param mixed $data 返回的数据
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function error($msg = '', $data = '', array $header = [], $code = 0)
    {

        if (is_array($msg)) {
            $code   = $msg['code'];
            $msg    = $msg['msg'];
        }
        $result = [
            'code'  => $code,
            'msg'   => $msg,

        ];
         
        $type = $this->getResponseType();

        $header['Access-Control-Allow-Origin']  = '*';
        $header['Access-Control-Allow-Headers'] = 'X-Requested-With,Content-Type,token';
        $header['Access-Control-Allow-Methods'] = 'GET,POST,PATCH,PUT,DELETE';

        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    // 获取Mid
    public function getUid()
    {
        return User::instance()->getUid();
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType()
    {
        return 'json';
    }
}